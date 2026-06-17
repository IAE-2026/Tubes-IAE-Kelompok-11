<?php

namespace App\Http\Middleware;

use App\Models\Role;
use App\Models\User;
use App\Services\JwksService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * SsoAuthenticate Middleware
 *
 * Intercepts incoming requests to protected API/GraphQL routes and:
 *
 * 1. Extracts the Bearer token from the Authorization header.
 * 2. Verifies the JWT signature using JWKS public keys from the SSO server.
 * 3. Maps (or creates) the external SSO user in the local 'users' table.
 * 4. Assigns a default local role if the user doesn't exist yet.
 * 5. Authenticates the user into the current Laravel request lifecycle
 *    so that Auth::user() and $request->user() work in controllers/resolvers.
 */
class SsoAuthenticate
{
    /**
     * The JWKS verification service.
     */
    protected JwksService $jwksService;

    public function __construct(JwksService $jwksService)
    {
        $this->jwksService = $jwksService;
    }

    /**
     * Handle an incoming request.
     *
     * @param  Request  $request
     * @param  Closure  $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        // ── Step 1: Extract Bearer token from Authorization header ──
        $token = $request->bearerToken();

        if (!$token) {
            Log::warning('[SSO Middleware] No Bearer token found in request.');
            return response()->json([
                'status'  => 'error',
                'message' => 'Unauthorized. Bearer token is required.',
            ], 401);
        }

        // ── Step 2: Verify the JWT using JWKS ──
        $payload = $this->jwksService->verifyToken($token);

        if (!$payload) {
            Log::warning('[SSO Middleware] JWT verification failed.');
            return response()->json([
                'status'  => 'error',
                'message' => 'Unauthorized. Invalid or expired token.',
            ], 401);
        }

        // ── DEBUG: Log the full decoded JWT payload so we can inspect its structure ──
        Log::info('[SSO Middleware] Decoded JWT Payload:', (array) $payload);

        // ── Step 3: Flexibly extract user identity from JWT claims ──
        // Different SSO servers use different claim names. We check multiple
        // possible locations in priority order to find the email, subject, and name.
        $email = $this->extractEmail($payload);
        $sub   = $this->extractSub($payload);
        $name  = $this->extractName($payload);

        // If we found an email but no explicit sub, derive sub from the email
        // (some lightweight SSO servers don't emit a 'sub' claim at all)
        if ($email && !$sub) {
            $sub = $email;
            Log::info('[SSO Middleware] No "sub" claim found; using email as sub.', ['sub' => $sub]);
        }

        // If we found a sub but no email, use sub as a synthetic email
        if ($sub && !$email) {
            $email = $sub;
            Log::info('[SSO Middleware] No "email" claim found; using sub as email.', ['email' => $email]);
        }

        // Final check — we need at least ONE usable identifier
        if (!$sub && !$email) {
            Log::error('[SSO Middleware] Could not extract any user identifier from JWT.', [
                'payload_keys' => array_keys((array) $payload),
            ]);
            return response()->json([
                'status'  => 'error',
                'message' => 'Unauthorized. Token payload contains no usable user identifier.',
            ], 401);
        }

        Log::info('[SSO Middleware] Extracted identity from JWT.', [
            'sub'   => $sub,
            'email' => $email,
            'name'  => $name,
        ]);

        // ── Step 4: Sync the external user to the local database ──
        $user = $this->syncUser($sub, $email, $name);

        // ── Step 5: Authenticate the user into the current request lifecycle ──
        // Set on the default guard (for Auth::user() / $request->user())
        Auth::setUser($user);
        // Set explicitly on the 'sso' guard (for Lighthouse's @guard(with: ["sso"]))
        Auth::guard('sso')->setUser($user);

        Log::info('[SSO Middleware] User authenticated successfully.', [
            'user_id' => $user->id,
            'email'   => $user->email,
            'role'    => $user->role?->name ?? 'none',
        ]);

        return $next($request);
    }

    /**
     * Extract email from the JWT payload by checking multiple possible claim names.
     *
     * Priority order:
     *  1. email            — Standard OIDC claim
     *  2. mail             — Microsoft / LDAP-style
     *  3. preferred_username — OIDC (often an email)
     *  4. upn              — Azure AD User Principal Name
     *  5. profile.email    — Nested profile object
     *  6. user.email       — Nested user object
     *  7. data.email       — Wrapped response
     *
     * @param  object  $payload  The decoded JWT payload.
     * @return string|null
     */
    protected function extractEmail(object $payload): ?string
    {
        // Top-level standard claims
        foreach (['email', 'mail', 'preferred_username', 'upn'] as $key) {
            if (!empty($payload->$key) && $this->looksLikeEmail((string) $payload->$key)) {
                return (string) $payload->$key;
            }
        }

        // Nested under common wrapper objects
        foreach (['profile', 'user', 'data'] as $wrapper) {
            if (isset($payload->$wrapper) && is_object($payload->$wrapper)) {
                foreach (['email', 'mail'] as $key) {
                    if (!empty($payload->$wrapper->$key)) {
                        return (string) $payload->$wrapper->$key;
                    }
                }
            }
        }

        // Last resort: if preferred_username looks email-ish, accept it even without @
        if (!empty($payload->preferred_username)) {
            return (string) $payload->preferred_username;
        }

        return null;
    }

    /**
     * Extract the subject (unique user ID) from the JWT payload.
     *
     * Priority order:
     *  1. sub         — Standard JWT claim
     *  2. user_id     — Common custom claim
     *  3. id          — Simple numeric ID
     *  4. uid         — Unix-style UID
     *  5. jti         — JWT ID (last resort)
     *  6. profile.id  — Nested profile
     *  7. user.id     — Nested user
     *  8. data.id     — Wrapped response
     *
     * @param  object  $payload
     * @return string|null
     */
    protected function extractSub(object $payload): ?string
    {
        // Top-level claims
        foreach (['sub', 'user_id', 'id', 'uid'] as $key) {
            if (isset($payload->$key) && $payload->$key !== '') {
                return (string) $payload->$key;
            }
        }

        // Nested under common wrapper objects
        foreach (['profile', 'user', 'data'] as $wrapper) {
            if (isset($payload->$wrapper) && is_object($payload->$wrapper)) {
                foreach (['sub', 'id', 'user_id'] as $key) {
                    if (isset($payload->$wrapper->$key) && $payload->$wrapper->$key !== '') {
                        return (string) $payload->$wrapper->$key;
                    }
                }
            }
        }

        // JWT ID as absolute last resort
        if (!empty($payload->jti)) {
            return (string) $payload->jti;
        }

        return null;
    }

    /**
     * Extract a display name from the JWT payload.
     *
     * @param  object  $payload
     * @return string|null
     */
    protected function extractName(object $payload): ?string
    {
        // Top-level claims
        foreach (['name', 'display_name', 'full_name', 'preferred_username', 'nickname', 'given_name'] as $key) {
            if (!empty($payload->$key)) {
                return (string) $payload->$key;
            }
        }

        // Nested
        foreach (['profile', 'user', 'data'] as $wrapper) {
            if (isset($payload->$wrapper) && is_object($payload->$wrapper)) {
                foreach (['name', 'display_name', 'full_name'] as $key) {
                    if (!empty($payload->$wrapper->$key)) {
                        return (string) $payload->$wrapper->$key;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Simple heuristic: does a string look like an email address?
     *
     * @param  string  $value
     * @return bool
     */
    protected function looksLikeEmail(string $value): bool
    {
        return str_contains($value, '@');
    }

    /**
     * Synchronise the external SSO user to the local 'users' table.
     *
     * Uses `email` as the primary identifier to find or create users.
     * This approach works with the standard Laravel users table schema
     * (no additional SSO columns required).
     *
     * @param string      $sub    The JWT subject (used for logging only).
     * @param string      $email  The user's email extracted from the JWT.
     * @param string|null $name   The user's display name from the JWT.
     * @return User
     */
    protected function syncUser(string $sub, string $email, ?string $name): User
    {
        // Look up the user by email (the standard unique identifier)
        $user = User::where('email', $email)->first();

        if ($user) {
            // Update the display name if the SSO provides a newer one
            if ($name && $name !== $user->name) {
                $user->update(['name' => $name]);
            }

            Log::info('[SSO Middleware] Existing user found by email.', [
                'user_id' => $user->id,
                'email'   => $email,
            ]);

            return $user;
        }

        // User doesn't exist yet — create a new local record
        $user = User::create([
            'name'     => $name ?? explode('@', $email)[0], // Fallback: email prefix as name
            'email'    => $email,
            'password' => bcrypt(Str::random(32)), // Random password; SSO users don't log in locally
        ]);

        Log::info('[SSO Middleware] New SSO user created.', [
            'user_id' => $user->id,
            'email'   => $email,
            'sub'     => $sub,
        ]);

        return $user;
    }
}

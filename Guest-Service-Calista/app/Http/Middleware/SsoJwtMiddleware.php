<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Services\SsoIntegrationService;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class SsoJwtMiddleware
{
    protected SsoIntegrationService $ssoService;

    public function __construct(SsoIntegrationService $ssoService)
    {
        $this->ssoService = $ssoService;
    }

    public function handle(Request $request, Closure $next): Response
    {
        $authHeader = $request->header('Authorization');

        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized: Missing or invalid Authorization Bearer token',
                'errors' => null
            ], 401);
        }

        $token = substr($authHeader, 7);

        try {

            $payload = $this->ssoService->verifySsoJwt($token);
            
            $tokenType = $payload['token_type'] ?? null;
            $sub = $payload['sub'] ?? null;

            if (!$tokenType || !$sub) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized: Invalid token claims',
                    'errors' => null
                ], 401);
            }

            $roleName = $tokenType === 'user' ? 'warga' : ($tokenType === 'm2m' ? 'm2m' : null);
            if (!$roleName) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized: Unknown token type role mapping',
                    'errors' => null
                ], 401);
            }

            $role = Role::where('name', $roleName)->first();
            if (!$role) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Server Error: Local role ' . $roleName . ' not found in database',
                    'errors' => null
                ], 500);
            }

            if ($tokenType === 'user') {
                $profile = $payload['profile'] ?? [];
                $email = $profile['email'] ?? $sub;
                $name = $profile['name'] ?? explode('@', $email)[0];
            } else {
                $app = $payload['app'] ?? [];
                $email = $sub . '@m2m.iae.id'; 
                $name = $app['name'] ?? 'M2M client ' . $sub;
            }
            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => bcrypt('SSO_AUTHENTICATED_EXTERNAL_USER'), 
                    'role_id' => $role->id,
                ]
            );

            Auth::login($user);

            $request->attributes->set('sso_payload', $payload);
            $request->attributes->set('sso_user', $user);

            return $next($request);

        } catch (\Exception $e) {
            Log::error("JWT Authentication Middleware error: " . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized: Invalid token verification (' . $e->getMessage() . ')',
                'errors' => null
            ], 401);
        }
    }
}

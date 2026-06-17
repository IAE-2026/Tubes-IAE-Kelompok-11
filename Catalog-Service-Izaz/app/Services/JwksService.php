<?php

namespace App\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\JWK;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use UnexpectedValueException;
use Exception;

/**
 * JwksService — JWT Verification via JWKS
 *
 * Responsible for:
 * 1. Fetching and caching the JWKS (JSON Web Key Set) from the central SSO server.
 * 2. Decoding and verifying incoming JWT Bearer tokens using the RS256 public keys.
 *
 * Security considerations:
 * - JWKS responses are cached to prevent excessive external API calls.
 * - Clock skew tolerance (leeway) handles minor time differences between servers.
 * - All exceptions are caught and logged; callers receive null on failure.
 */
class JwksService
{
    /**
     * The JWKS endpoint URL.
     */
    protected string $jwksUrl;

    /**
     * Cache TTL for the JWKS response in seconds.
     */
    protected int $cacheTtl;

    /**
     * Accepted JWT signing algorithms.
     *
     * @var list<string>
     */
    protected array $algorithms;

    /**
     * Clock skew tolerance in seconds.
     */
    protected int $leeway;

    public function __construct()
    {
        $this->jwksUrl    = config('sso.jwks_url');
        $this->cacheTtl   = (int) config('sso.jwks_cache_ttl', 3600);
        $this->algorithms = config('sso.algorithms', ['RS256']);
        $this->leeway     = (int) config('sso.leeway_seconds', 60);
    }

    /**
     * Fetch the JWKS public keys from the SSO server.
     *
     * The response is cached for `jwks_cache_ttl` seconds so that
     * every incoming request doesn't trigger an external HTTP call.
     *
     * @return array<string, Key>  Parsed keys indexed by 'kid'
     *
     * @throws Exception  If the JWKS endpoint is unreachable or returns invalid data.
     */
    public function getPublicKeys(): array
    {
        $jwks = Cache::remember('sso_jwks_keys', $this->cacheTtl, function () {
            Log::info('[SSO] Fetching JWKS from: ' . $this->jwksUrl);

            $response = Http::timeout(10)
                ->retry(2, 500)
                ->get($this->jwksUrl);

            if (!$response->successful()) {
                Log::error('[SSO] JWKS fetch failed', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                throw new Exception('Failed to fetch JWKS from SSO server. HTTP ' . $response->status());
            }

            $data = $response->json();

            if (!isset($data['keys']) || empty($data['keys'])) {
                Log::error('[SSO] JWKS response missing "keys" array', ['body' => $data]);
                throw new Exception('JWKS response does not contain a valid "keys" array.');
            }

            Log::info('[SSO] JWKS fetched successfully', ['key_count' => count($data['keys'])]);

            return $data;
        });

        // Parse the raw JWKS JSON into Key objects that firebase/php-jwt understands
        return JWK::parseKeySet($jwks, $this->algorithms[0] ?? 'RS256');
    }

    /**
     * Decode and verify a JWT Bearer token.
     *
     * @param string $token  The raw JWT string (without "Bearer " prefix).
     * @return object|null   The decoded payload on success, or null on failure.
     */
    public function verifyToken(string $token): ?object
    {
        try {
            // Apply clock skew tolerance
            JWT::$leeway = $this->leeway;

            $keys    = $this->getPublicKeys();
            $decoded = JWT::decode($token, $keys);

            Log::info('[SSO] JWT verified successfully', [
                'sub'   => $decoded->sub ?? 'N/A',
                'email' => $decoded->email ?? 'N/A',
            ]);

            return $decoded;
        } catch (UnexpectedValueException $e) {
            // Covers: expired tokens, signature mismatch, malformed tokens
            Log::warning('[SSO] JWT verification failed', [
                'error' => $e->getMessage(),
            ]);

            // If verification fails, try clearing the cached JWKS and retry once.
            // The SSO server may have rotated its keys.
            return $this->retryWithFreshKeys($token, $e);
        } catch (Exception $e) {
            Log::error('[SSO] Unexpected error during JWT verification', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Retry token verification after flushing the cached JWKS.
     *
     * This handles the edge case where the SSO server has rotated its
     * signing keys but we are still using a stale cached copy.
     *
     * @param string    $token      The JWT to re-verify.
     * @param Exception $original   The original exception for context.
     * @return object|null
     */
    protected function retryWithFreshKeys(string $token, Exception $original): ?object
    {
        try {
            Log::info('[SSO] Retrying JWT verification with fresh JWKS keys...');
            Cache::forget('sso_jwks_keys');

            $keys    = $this->getPublicKeys();
            $decoded = JWT::decode($token, $keys);

            Log::info('[SSO] JWT verified successfully after key refresh', [
                'sub' => $decoded->sub ?? 'N/A',
            ]);

            return $decoded;
        } catch (Exception $retryException) {
            Log::warning('[SSO] JWT verification failed after key refresh', [
                'original_error' => $original->getMessage(),
                'retry_error'    => $retryException->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Force-clear the cached JWKS keys.
     * Useful for admin/debug endpoints.
     */
    public function clearCache(): void
    {
        Cache::forget('sso_jwks_keys');
        Log::info('[SSO] JWKS cache cleared manually.');
    }
}

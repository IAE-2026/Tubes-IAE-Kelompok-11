<?php

namespace App\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\JWK;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SsoIntegrationService
{
    protected string $jwksUrl;
    protected string $apiKey;

    public function __construct()
    {
        $this->jwksUrl = env('CENTRAL_SSO_JWKS_URL', 'https://iae-sso.virtualfri.id/api/v1/auth/jwks');
        $this->apiKey = env('CENTRAL_API_KEY', 'KEY-MHS-346');
    }

    public function getServiceToken(): string
    {
        $cacheKey = 'central_sso_m2m_token_' . md5($this->apiKey);

        return Cache::remember($cacheKey, 3000, function () {
            Log::info("Fetching new service token from SSO...");
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post('https://iae-sso.virtualfri.id/api/v1/auth/token', [
                'api_key' => $this->apiKey,
            ]);

            if ($response->failed()) {
                Log::error("Failed to authenticate with SSO Dosen: " . $response->body());
                throw new \Exception('Failed to authenticate with SSO central infrastructure.');
            }

            $token = $response->json('token');
            if (!$token) {
                throw new \Exception('No token returned from SSO server.');
            }

            return $token;
        });
    }


    public function verifySsoJwt(string $token): array
    {
        $jwks = Cache::remember('sso_jwks_keys', 86400, function () { 
            Log::info("Fetching JWKS keys from SSO...");
            $response = Http::get($this->jwksUrl);
            if ($response->failed()) {
                throw new \Exception('Failed to fetch JWKS from SSO server');
            }
            return $response->json();
        });

        try {
            $keys = JWK::parseKeySet($jwks);
            $decoded = JWT::decode($token, $keys);
            return json_decode(json_encode($decoded), true);
        } catch (\Exception $e) {
            Log::warning("SSO JWT verification failed: " . $e->getMessage() . ". Retrying with fresh JWKS...");
            
            Cache::forget('sso_jwks_keys');
            $jwks = Cache::remember('sso_jwks_keys', 86400, function () {
                return Http::get($this->jwksUrl)->json();
            });

            $keys = JWK::parseKeySet($jwks);
            $decoded = JWT::decode($token, $keys);
            return json_decode(json_encode($decoded), true);
        }
    }
}

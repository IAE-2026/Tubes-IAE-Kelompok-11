<?php

return [

    /*
    |--------------------------------------------------------------------------
    | SSO Server Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the central SSO server (Cloud Dosen / IAE SSO).
    | These settings control how the application verifies JWT tokens
    | issued by the external identity provider.
    |
    */

    // Base URL of the central SSO identity provider
    'base_url' => env('SSO_BASE_URL', 'https://iae-sso.virtualfri.id'),

    // JWKS endpoint path — used to fetch RS256 public keys for JWT verification
    'jwks_url' => env('SSO_JWKS_URL', 'https://iae-sso.virtualfri.id/.well-known/jwks.json'),

    // How long (in seconds) to cache the JWKS response (default: 1 hour)
    // This avoids hitting the external API on every single request.
    'jwks_cache_ttl' => env('SSO_JWKS_CACHE_TTL', 3600),

    // Accepted signing algorithms (RS256 is standard for JWKS)
    'algorithms' => ['RS256'],

    // The default local role name to assign when a new SSO user is synced
    // for the first time. Must match a row in the 'roles' table.
    'default_role' => env('SSO_DEFAULT_ROLE', 'viewer'),

    // Clock skew tolerance in seconds (handles minor clock differences
    // between the SSO server and this application server)
    'leeway_seconds' => env('SSO_LEEWAY_SECONDS', 60),

];

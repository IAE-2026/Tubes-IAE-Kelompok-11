<?php

namespace App\GraphQL\Queries;

use Illuminate\Support\Facades\Auth;

/**
 * Me Query Resolver
 *
 * Resolves the `me` GraphQL query by returning the currently
 * authenticated user (set by the SsoAuthenticate middleware).
 *
 * The @guard(with: ["sso"]) directive in the schema ensures this
 * resolver is only called when a valid SSO user is authenticated.
 */
class Me
{
    /**
     * Return the currently authenticated user with their role.
     *
     * @param  null  $_
     * @param  array{}  $args
     * @return \App\Models\User|null
     */
    public function __invoke($_, array $args)
    {
        $user = Auth::user();

        if ($user) {
            // Eager-load the role relationship so it's available in the response
            $user->load('role');
        }

        return $user;
    }
}

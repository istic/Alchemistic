<?php

namespace App\Http\Controllers\OAuth;

use Illuminate\Http\JsonResponse;

class DiscoveryController
{
    public function __invoke(): JsonResponse
    {
        $issuer = config('app.url');

        return response()->json([
            'issuer' => $issuer,
            'authorization_endpoint' => "{$issuer}/oauth/authorize",
            'token_endpoint' => "{$issuer}/oauth/token",
            'userinfo_endpoint' => "{$issuer}/oauth/userinfo",
            'jwks_uri' => "{$issuer}/oauth/jwks",
            'response_types_supported' => ['code'],
            'subject_types_supported' => ['public'],
            'id_token_signing_alg_values_supported' => ['RS256'],
            'scopes_supported' => ['openid', 'profile', 'email'],
            'token_endpoint_auth_methods_supported' => ['client_secret_post', 'none'],
            'claims_supported' => ['sub', 'name', 'email', 'email_verified', 'permissions'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'code_challenge_methods_supported' => ['S256'],
        ]);
    }
}

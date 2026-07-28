<?php

use App\Services\Oidc\OidcKey;

test('discovery document exposes the expected endpoints', function () {
    $response = $this->getJson('/.well-known/openid-configuration');

    $response->assertOk()->assertJson([
        'issuer' => config('app.url'),
        'authorization_endpoint' => config('app.url').'/oauth/authorize',
        'token_endpoint' => config('app.url').'/oauth/token',
        'userinfo_endpoint' => config('app.url').'/oauth/userinfo',
        'jwks_uri' => config('app.url').'/oauth/jwks',
    ]);
});

test('discovery document exposes the full set of supported capabilities', function () {
    $response = $this->getJson('/.well-known/openid-configuration');

    $response->assertOk()->assertJson([
        'response_types_supported' => ['code'],
        'subject_types_supported' => ['public'],
        'id_token_signing_alg_values_supported' => ['RS256'],
        'scopes_supported' => ['openid', 'profile', 'email'],
        'token_endpoint_auth_methods_supported' => ['client_secret_post', 'none'],
        'claims_supported' => ['sub', 'name', 'email', 'email_verified', 'permissions', 'nonce'],
        'grant_types_supported' => ['authorization_code', 'refresh_token'],
        'code_challenge_methods_supported' => ['S256'],
    ]);
});

test('jwks endpoint exposes the signing key', function () {
    $response = $this->getJson('/oauth/jwks');

    $response->assertOk()->assertJson([
        'keys' => [OidcKey::jwk()],
    ]);
});

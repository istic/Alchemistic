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

test('jwks endpoint exposes the signing key', function () {
    $response = $this->getJson('/oauth/jwks');

    $response->assertOk()->assertJson([
        'keys' => [OidcKey::jwk()],
    ]);
});

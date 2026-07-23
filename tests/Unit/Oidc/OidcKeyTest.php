<?php

use App\Services\Oidc\OidcKey;

test('jwk exposes the passport public key as an RSA JWK', function () {
    $jwk = OidcKey::jwk();

    expect($jwk)->toMatchArray([
        'kty' => 'RSA',
        'use' => 'sig',
        'alg' => 'RS256',
    ]);
    expect($jwk['kid'])->toBeString()->not->toBeEmpty();
    expect($jwk['n'])->toBeString()->not->toBeEmpty();
    expect($jwk['e'])->toBeString()->not->toBeEmpty();
});

test('kid is stable across calls', function () {
    expect(OidcKey::kid())->toBe(OidcKey::kid());
});

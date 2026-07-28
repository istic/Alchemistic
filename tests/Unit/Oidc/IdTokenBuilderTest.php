<?php

use App\Models\Permission;
use App\Models\User;
use App\Services\Oidc\IdTokenBuilder;
use App\Services\Oidc\OidcKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\Validator;

uses(RefreshDatabase::class);

test('builds a signed id_token with the expected claims', function () {
    $user = User::factory()->unverified()->create(['name' => 'Ada Lovelace', 'email' => 'ada@example.test']);
    $permission = Permission::create(['name' => 'admin', 'label' => 'Administrator']);
    $user->permissions()->attach($permission);

    $jwt = (new IdTokenBuilder)->build($user, 'client-123');

    $token = (new Parser(new JoseEncoder))->parse($jwt);

    $publicKey = InMemory::plainText(OidcKey::publicKeyPem());
    expect((new Validator)->validate($token, new SignedWith(new Sha256, $publicKey)))->toBeTrue();

    expect($token->claims()->get('sub'))->toBe((string) $user->id);
    expect($token->claims()->get('aud'))->toContain('client-123');
    expect($token->claims()->get('name'))->toBe('Ada Lovelace');
    expect($token->claims()->get('email'))->toBe('ada@example.test');
    expect($token->claims()->get('email_verified'))->toBeFalse();
    expect($token->claims()->get('permissions'))->toBe(['admin']);
});

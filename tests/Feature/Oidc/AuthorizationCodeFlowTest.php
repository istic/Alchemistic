<?php

use App\Models\Permission;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\Validator;

test('authorization code with PKCE issues tokens including an id_token with permissions', function () {
    $user = User::factory()->create();
    $permission = Permission::create(['name' => 'admin', 'label' => 'Administrator']);
    $user->permissions()->attach($permission);

    $client = app(ClientRepository::class)->createAuthorizationCodeGrantClient(
        name: 'Test App',
        redirectUris: ['https://example.test/callback'],
        confidential: false,
    );

    $codeVerifier = Str::random(128);
    $codeChallenge = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');

    $authorizeResponse = $this->actingAs($user)->get('/oauth/authorize?'.http_build_query([
        'client_id' => $client->getKey(),
        'redirect_uri' => 'https://example.test/callback',
        'response_type' => 'code',
        'scope' => 'openid profile email',
        'state' => 'xyz',
        'code_challenge' => $codeChallenge,
        'code_challenge_method' => 'S256',
    ]));

    $authorizeResponse->assertRedirect();
    parse_str(parse_url($authorizeResponse->headers->get('Location'), PHP_URL_QUERY), $query);
    expect($query)->toHaveKey('code');
    expect($query['state'])->toBe('xyz');

    $tokenResponse = $this->post('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $client->getKey(),
        'redirect_uri' => 'https://example.test/callback',
        'code_verifier' => $codeVerifier,
        'code' => $query['code'],
    ]);

    $tokenResponse->assertOk();
    $tokenResponse->assertJsonStructure(['access_token', 'refresh_token', 'id_token', 'expires_in']);

    $idToken = (new Parser(new JoseEncoder))->parse($tokenResponse->json('id_token'));

    $publicKey = InMemory::plainText(file_get_contents(Passport::keyPath('oauth-public.key')));
    expect((new Validator)->validate($idToken, new SignedWith(new Sha256, $publicKey)))->toBeTrue();

    expect($idToken->claims()->get('sub'))->toBe((string) $user->id);
    expect($idToken->claims()->get('email'))->toBe($user->email);
    expect($idToken->claims()->get('permissions'))->toBe(['admin']);
});

test('a non-first-party client does not skip the consent screen', function () {
    $user = User::factory()->create();

    $client = app(ClientRepository::class)->createAuthorizationCodeGrantClient(
        name: 'Third Party App',
        redirectUris: ['https://third-party.test/callback'],
        confidential: false,
        user: $user,
    );

    $response = $this->actingAs($user)->get('/oauth/authorize?'.http_build_query([
        'client_id' => $client->getKey(),
        'redirect_uri' => 'https://third-party.test/callback',
        'response_type' => 'code',
        'scope' => 'openid',
        'state' => 'xyz',
        'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', Str::random(128), true)), '+/', '-_'), '='),
        'code_challenge_method' => 'S256',
    ]));

    $response->assertOk();
    $response->assertDontSee('id_token');
});

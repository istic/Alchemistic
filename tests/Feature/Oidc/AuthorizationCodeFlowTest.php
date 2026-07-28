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
use Tests\TestCase;

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

    // Not silently granted: no authorization code was ever issued.
    expect($response->headers->get('Location'))->toBeNull();
});

test('the token endpoint rejects grant types other than authorization_code and refresh_token', function () {
    $client = app(ClientRepository::class)->createClientCredentialsGrantClient('Machine Client');

    $response = $this->post('/oauth/token', [
        'grant_type' => 'client_credentials',
        'client_id' => $client->getKey(),
        'client_secret' => $client->plainSecret,
    ]);

    $response->assertStatus(400)->assertJson(['error' => 'unsupported_grant_type']);
});

function pkcePair(): array
{
    $verifier = Str::random(128);
    $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

    return [$verifier, $challenge];
}

function authorizeAndGetCode(TestCase $testCase, User $user, $client, string $redirectUri, string $codeChallenge, string $scope = 'openid profile email'): string
{
    $response = $testCase->actingAs($user)->get('/oauth/authorize?'.http_build_query([
        'client_id' => $client->getKey(),
        'redirect_uri' => $redirectUri,
        'response_type' => 'code',
        'scope' => $scope,
        'state' => 'xyz',
        'code_challenge' => $codeChallenge,
        'code_challenge_method' => 'S256',
    ]));

    parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);

    return $query['code'];
}

test('the token endpoint rejects a mismatched PKCE code_verifier', function () {
    $user = User::factory()->create();
    $client = app(ClientRepository::class)->createAuthorizationCodeGrantClient(
        name: 'Test App',
        redirectUris: ['https://example.test/callback'],
        confidential: false,
    );

    [$codeVerifier, $codeChallenge] = pkcePair();
    $code = authorizeAndGetCode($this, $user, $client, 'https://example.test/callback', $codeChallenge);

    $response = $this->post('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $client->getKey(),
        'redirect_uri' => 'https://example.test/callback',
        'code_verifier' => Str::random(128),
        'code' => $code,
    ]);

    $response->assertStatus(400)->assertJson(['error' => 'invalid_grant']);
});

test('an authorization code cannot be redeemed twice', function () {
    $user = User::factory()->create();
    $client = app(ClientRepository::class)->createAuthorizationCodeGrantClient(
        name: 'Test App',
        redirectUris: ['https://example.test/callback'],
        confidential: false,
    );

    [$codeVerifier, $codeChallenge] = pkcePair();
    $code = authorizeAndGetCode($this, $user, $client, 'https://example.test/callback', $codeChallenge);

    $tokenParams = [
        'grant_type' => 'authorization_code',
        'client_id' => $client->getKey(),
        'redirect_uri' => 'https://example.test/callback',
        'code_verifier' => $codeVerifier,
        'code' => $code,
    ];

    $this->post('/oauth/token', $tokenParams)->assertOk();

    $replay = $this->post('/oauth/token', $tokenParams);
    $replay->assertStatus(400)->assertJson(['error' => 'invalid_grant']);
});

test('a revoked authorization code is rejected', function () {
    $user = User::factory()->create();
    $client = app(ClientRepository::class)->createAuthorizationCodeGrantClient(
        name: 'Test App',
        redirectUris: ['https://example.test/callback'],
        confidential: false,
    );

    [$codeVerifier, $codeChallenge] = pkcePair();
    $code = authorizeAndGetCode($this, $user, $client, 'https://example.test/callback', $codeChallenge);

    Passport::authCode()->newQuery()->update(['revoked' => true]);

    $response = $this->post('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $client->getKey(),
        'redirect_uri' => 'https://example.test/callback',
        'code_verifier' => $codeVerifier,
        'code' => $code,
    ]);

    $response->assertStatus(400)->assertJson(['error' => 'invalid_grant']);
});

test('the id_token echoes back the nonce supplied at the authorize step', function () {
    $user = User::factory()->create();
    $client = app(ClientRepository::class)->createAuthorizationCodeGrantClient(
        name: 'Test App',
        redirectUris: ['https://example.test/callback'],
        confidential: false,
    );

    [$codeVerifier, $codeChallenge] = pkcePair();

    $authorizeResponse = $this->actingAs($user)->get('/oauth/authorize?'.http_build_query([
        'client_id' => $client->getKey(),
        'redirect_uri' => 'https://example.test/callback',
        'response_type' => 'code',
        'scope' => 'openid',
        'state' => 'xyz',
        'nonce' => 'nonce-value-123',
        'code_challenge' => $codeChallenge,
        'code_challenge_method' => 'S256',
    ]));

    parse_str(parse_url($authorizeResponse->headers->get('Location'), PHP_URL_QUERY), $query);

    $tokenResponse = $this->post('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $client->getKey(),
        'redirect_uri' => 'https://example.test/callback',
        'code_verifier' => $codeVerifier,
        'code' => $query['code'],
    ]);

    $tokenResponse->assertOk();

    $idToken = (new Parser(new JoseEncoder))->parse($tokenResponse->json('id_token'));

    expect($idToken->claims()->get('nonce'))->toBe('nonce-value-123');
});

test('the token response omits id_token when openid scope is not requested', function () {
    $user = User::factory()->create();
    $client = app(ClientRepository::class)->createAuthorizationCodeGrantClient(
        name: 'Test App',
        redirectUris: ['https://example.test/callback'],
        confidential: false,
    );

    [$codeVerifier, $codeChallenge] = pkcePair();
    $code = authorizeAndGetCode($this, $user, $client, 'https://example.test/callback', $codeChallenge, scope: 'profile email');

    $tokenResponse = $this->post('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $client->getKey(),
        'redirect_uri' => 'https://example.test/callback',
        'code_verifier' => $codeVerifier,
        'code' => $code,
    ]);

    $tokenResponse->assertOk();
    expect($tokenResponse->json('id_token'))->toBeNull();
});

test('a refresh token can be exchanged for a new access token and id_token, and cannot be reused', function () {
    $user = User::factory()->create();
    $client = app(ClientRepository::class)->createAuthorizationCodeGrantClient(
        name: 'Test App',
        redirectUris: ['https://example.test/callback'],
        confidential: false,
    );

    [$codeVerifier, $codeChallenge] = pkcePair();
    $code = authorizeAndGetCode($this, $user, $client, 'https://example.test/callback', $codeChallenge);

    $initialTokenResponse = $this->post('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $client->getKey(),
        'redirect_uri' => 'https://example.test/callback',
        'code_verifier' => $codeVerifier,
        'code' => $code,
    ]);

    $initialTokenResponse->assertOk();
    $refreshToken = $initialTokenResponse->json('refresh_token');

    $refreshParams = [
        'grant_type' => 'refresh_token',
        'client_id' => $client->getKey(),
        'refresh_token' => $refreshToken,
    ];

    $refreshResponse = $this->post('/oauth/token', $refreshParams);

    $refreshResponse->assertOk();
    $refreshResponse->assertJsonStructure(['access_token', 'refresh_token', 'id_token', 'expires_in']);
    expect($refreshResponse->json('access_token'))->not->toBe($initialTokenResponse->json('access_token'));
    expect($refreshResponse->json('refresh_token'))->not->toBe($refreshToken);

    $idToken = (new Parser(new JoseEncoder))->parse($refreshResponse->json('id_token'));
    expect($idToken->claims()->get('sub'))->toBe((string) $user->id);

    // The used refresh token is revoked and cannot be exchanged again.
    $replay = $this->post('/oauth/token', $refreshParams);
    $replay->assertStatus(400)->assertJson(['error' => 'invalid_grant']);
});

# Alchemistic OAuth/OIDC Provider Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make Alchemistic an OpenID Connect provider so first-party Istic services can authenticate users via "Log in with Alchemistic" (Authorization Code + PKCE), with the user's Alchemistic permissions carried as a custom claim.

**Architecture:** Laravel Passport (`laravel/passport` ^13.0) handles OAuth2 mechanics (clients, codes, tokens, revocation). A thin custom layer adds OIDC on top: an `id_token` minted via an event listener and spliced into Passport's token response by a controller that extends Passport's own, plus new `/oauth/userinfo`, `/oauth/jwks`, and `/.well-known/openid-configuration` endpoints. Only the Authorization Code + PKCE grant is enabled; first-party clients skip the consent screen.

**Tech Stack:** Laravel 12, Laravel Passport ^13.0 (brings `league/oauth2-server` and `lcobucci/jwt` ^5.6 transitively), Pest 4.

Full design rationale: `docs/superpowers/specs/2026-07-23-oauth-provider-design.md`

---

### Task 1: Install and configure Laravel Passport

**Files:**
- Modify: `composer.json` (via `composer require`)
- Modify: `config/auth.php`

- [ ] **Step 1: Require and install Passport**

```bash
vendor/bin/sail composer require laravel/passport
vendor/bin/sail artisan install:api --passport --no-interaction
```

This publishes and runs Passport's migrations (`oauth_clients`, `oauth_auth_codes`, `oauth_access_tokens`, `oauth_refresh_tokens`, `oauth_device_codes`, `oauth_personal_access_clients`) and generates `storage/oauth-private.key` / `storage/oauth-public.key`. We don't use device codes or personal access clients, but leaving their (unused) tables migrated is the standard Passport install — don't hand-edit the published migration.

- [ ] **Step 2: Add the `api` guard**

In `config/auth.php`, add to the `guards` array (after `web`):

```php
        'api' => [
            'driver' => 'passport',
            'provider' => 'users',
        ],
```

- [ ] **Step 3: Verify the install**

```bash
vendor/bin/sail artisan migrate:status | grep oauth
ls storage/oauth-private.key storage/oauth-public.key
```

Expected: all `oauth_*` migrations show `Ran`, and both key files exist.

- [ ] **Step 4: Commit**

```bash
git add composer.json composer.lock config/auth.php database/migrations
git commit -m "🎇 Install Laravel Passport"
```

---

### Task 2: Make the User model Passport-aware

**Files:**
- Modify: `app/Models/User.php`

- [ ] **Step 1: Add the trait and interface**

```php
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;

class User extends Authenticatable implements OAuthenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, TwoFactorAuthenticatable;
```

(Add the two new `use` imports alongside the existing ones, and add `HasApiTokens` to the trait list and `implements OAuthenticatable` to the class declaration. Leave the rest of the file untouched.)

- [ ] **Step 2: Verify it boots**

```bash
vendor/bin/sail artisan tinker --execute="echo App\Models\User::factory()->make()->getAuthIdentifier();"
```

Expected: prints a value with no fatal error (confirms the trait didn't break the model).

- [ ] **Step 3: Commit**

```bash
git add app/Models/User.php
git commit -m "🎇 Add Passport token support to User model"
```

---

### Task 3: First-party client model (skip consent)

**Files:**
- Create: `app/Models/Passport/Client.php`

- [ ] **Step 1: Write the model**

```php
<?php

namespace App\Models\Passport;

use Illuminate\Contracts\Auth\Authenticatable;
use Laravel\Passport\Client as BaseClient;

class Client extends BaseClient
{
    /**
     * Determine if the client should skip the authorization prompt.
     *
     * @param  \Laravel\Passport\Scope[]  $scopes
     */
    public function skipsAuthorization(Authenticatable $user, array $scopes): bool
    {
        return $this->firstParty();
    }
}
```

Clients created via `artisan passport:client` (no owning user) are "first party" per Passport's own `firstParty()` check (`empty($this->user_id)`). This is wired into Passport in Task 4.

- [ ] **Step 2: Commit**

```bash
git add app/Models/Passport/Client.php
git commit -m "🎇 Add first-party OAuth client model that skips consent"
```

---

### Task 4: Wire Passport configuration in AppServiceProvider

**Files:**
- Modify: `app/Providers/AppServiceProvider.php`

- [ ] **Step 1: Ignore Passport's default routes and bind the custom client model**

Passport registers its own `/oauth/*` routes when it boots, before `routes/web.php` loads — if we don't disable them, they'd shadow ours. `Passport::ignoreRoutes()` must be called from `register()`, before boot:

```php
<?php

namespace App\Providers;

use App\Listeners\AttachOidcIdToken;
use App\Models\Passport\Client;
use App\Models\SftpUser;
use App\Observers\SftpUserObserver;
use App\Services\Oidc\PendingIdToken;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Passport\Events\AccessTokenCreated;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        Passport::ignoreRoutes();

        $this->app->singleton(PendingIdToken::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        \Illuminate\Support\Facades\URL::forceScheme('https');
        SftpUser::observe(SftpUserObserver::class);

        Passport::useClientModel(Client::class);

        Passport::tokensCan([
            'openid' => 'Verify your identity',
            'profile' => 'View your name',
            'email' => 'View your email address',
        ]);

        Passport::defaultScopes(['openid']);

        Event::listen(AccessTokenCreated::class, AttachOidcIdToken::class);
    }
```

(Keep `configureDefaults()` as-is below.) `PendingIdToken` and `AttachOidcIdToken` don't exist yet — they're created in Task 6. This step will not be runnable in isolation until Task 6 lands; that's expected, do Tasks 5 and 6 next before verifying.

- [ ] **Step 2: Commit**

```bash
git add app/Providers/AppServiceProvider.php
git commit -m "🎇 Configure Passport scopes, client model, and route overrides"
```

---

### Task 5: OIDC signing key helper

**Files:**
- Create: `app/Services/Oidc/OidcKey.php`
- Test: `tests/Unit/Oidc/OidcKeyTest.php`

Passport generates an RSA key pair at install time (`storage/oauth-private.key` / `oauth-public.key`) for signing its own access tokens. We reuse that same key pair to sign `id_token`s, and need to expose the public half as a JWK for the `/oauth/jwks` endpoint (Passport has no JWKS endpoint of its own).

- [ ] **Step 1: Write the failing test**

```php
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
```

- [ ] **Step 2: Run test to verify it fails**

```bash
vendor/bin/sail artisan test --compact tests/Unit/Oidc/OidcKeyTest.php
```

Expected: FAIL with "Class App\Services\Oidc\OidcKey not found".

- [ ] **Step 3: Write the implementation**

```php
<?php

namespace App\Services\Oidc;

use Laravel\Passport\Passport;

class OidcKey
{
    public static function publicKeyPem(): string
    {
        return file_get_contents(Passport::keyPath('oauth-public.key'));
    }

    public static function kid(): string
    {
        return substr(hash('sha256', self::publicKeyPem()), 0, 16);
    }

    /**
     * @return array<string, string>
     */
    public static function jwk(): array
    {
        $details = openssl_pkey_get_details(openssl_pkey_get_public(self::publicKeyPem()));

        return [
            'kty' => 'RSA',
            'use' => 'sig',
            'alg' => 'RS256',
            'kid' => self::kid(),
            'n' => rtrim(strtr(base64_encode($details['rsa']['n']), '+/', '-_'), '='),
            'e' => rtrim(strtr(base64_encode($details['rsa']['e']), '+/', '-_'), '='),
        ];
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

```bash
vendor/bin/sail artisan test --compact tests/Unit/Oidc/OidcKeyTest.php
```

Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Services/Oidc/OidcKey.php tests/Unit/Oidc/OidcKeyTest.php
git commit -m "🎇 Add OIDC signing key / JWK helper"
```

---

### Task 6: id_token builder, pending-token holder, and listener

**Files:**
- Create: `app/Services/Oidc/IdTokenBuilder.php`
- Create: `app/Services/Oidc/PendingIdToken.php`
- Create: `app/Listeners/AttachOidcIdToken.php`
- Test: `tests/Unit/Oidc/IdTokenBuilderTest.php`

Passport's token endpoint doesn't know about `id_token`s. We generate one ourselves, in an `AccessTokenCreated` event listener (fired synchronously, within the same request, right after Passport persists the access token row — see `Laravel\Passport\Bridge\AccessTokenRepository::persistNewAccessToken`), and stash it in a request-scoped singleton that the token controller (Task 8) reads back after calling into Passport.

- [ ] **Step 1: Write the failing test for IdTokenBuilder**

```php
<?php

use App\Models\Permission;
use App\Models\User;
use App\Services\Oidc\IdTokenBuilder;
use App\Services\Oidc\OidcKey;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\Validator;

test('builds a signed id_token with the expected claims', function () {
    $user = User::factory()->create(['name' => 'Ada Lovelace', 'email' => 'ada@example.test']);
    $permission = Permission::create(['name' => 'admin', 'label' => 'Administrator']);
    $user->permissions()->attach($permission);

    $jwt = (new IdTokenBuilder())->build($user, 'client-123');

    $token = (new Parser(new JoseEncoder()))->parse($jwt);

    $publicKey = InMemory::plainText(OidcKey::publicKeyPem());
    expect((new Validator())->validate($token, new SignedWith(new Sha256(), $publicKey)))->toBeTrue();

    expect($token->claims()->get('sub'))->toBe((string) $user->id);
    expect($token->claims()->get('aud'))->toContain('client-123');
    expect($token->claims()->get('name'))->toBe('Ada Lovelace');
    expect($token->claims()->get('email'))->toBe('ada@example.test');
    expect($token->claims()->get('email_verified'))->toBeFalse();
    expect($token->claims()->get('permissions'))->toBe(['admin']);
});
```

- [ ] **Step 2: Run test to verify it fails**

```bash
vendor/bin/sail artisan test --compact tests/Unit/Oidc/IdTokenBuilderTest.php
```

Expected: FAIL with "Class App\Services\Oidc\IdTokenBuilder not found".

- [ ] **Step 3: Write IdTokenBuilder**

```php
<?php

namespace App\Services\Oidc;

use App\Models\User;
use DateTimeImmutable;
use Laravel\Passport\Passport;
use Lcobucci\JWT\Encoding\ChainedFormatter;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token\Builder;

class IdTokenBuilder
{
    public function build(User $user, string $clientId): string
    {
        $now = new DateTimeImmutable();

        $builder = (new Builder(new JoseEncoder(), ChainedFormatter::default()))
            ->issuedBy(config('app.url'))
            ->permittedFor($clientId)
            ->relatedTo((string) $user->getKey())
            ->issuedAt($now)
            ->expiresAt($now->modify('+1 hour'))
            ->withHeader('kid', OidcKey::kid())
            ->withClaim('name', $user->name)
            ->withClaim('email', $user->email)
            ->withClaim('email_verified', $user->email_verified_at !== null)
            ->withClaim('permissions', $user->permissions()->pluck('name')->values()->all());

        return $builder
            ->getToken(new Sha256(), InMemory::plainText(
                file_get_contents(Passport::keyPath('oauth-private.key'))
            ))
            ->toString();
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

```bash
vendor/bin/sail artisan test --compact tests/Unit/Oidc/IdTokenBuilderTest.php
```

Expected: PASS.

- [ ] **Step 5: Write PendingIdToken and the listener (no dedicated test — covered by the end-to-end flow test in Task 9)**

```php
<?php

namespace App\Services\Oidc;

class PendingIdToken
{
    public ?string $token = null;
}
```

```php
<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\Oidc\IdTokenBuilder;
use App\Services\Oidc\PendingIdToken;
use Laravel\Passport\Events\AccessTokenCreated;
use Laravel\Passport\Passport;

class AttachOidcIdToken
{
    public function __construct(
        private readonly IdTokenBuilder $idTokenBuilder,
        private readonly PendingIdToken $pendingIdToken,
    ) {
    }

    public function handle(AccessTokenCreated $event): void
    {
        if ($event->userId === null) {
            return;
        }

        $token = Passport::token()->find($event->tokenId);

        if ($token === null || ! in_array('openid', $token->scopes, true)) {
            return;
        }

        $user = User::find($event->userId);

        if ($user === null) {
            return;
        }

        $this->pendingIdToken->token = $this->idTokenBuilder->build($user, $event->clientId);
    }
}
```

`PendingIdToken` is bound as a singleton in `AppServiceProvider::register()` (Task 4), so the listener (resolved when the event fires) and the token controller (resolved when the request comes in, Task 8) share the same instance within a single HTTP request.

- [ ] **Step 6: Commit**

```bash
git add app/Services/Oidc/IdTokenBuilder.php app/Services/Oidc/PendingIdToken.php app/Listeners/AttachOidcIdToken.php tests/Unit/Oidc/IdTokenBuilderTest.php
git commit -m "🎇 Add id_token builder and AccessTokenCreated listener"
```

---

### Task 7: Custom token controller (splice id_token into Passport's response)

**Files:**
- Create: `app/Http/Controllers/OAuth/TokenController.php`

- [ ] **Step 1: Write the controller**

```php
<?php

namespace App\Http\Controllers\OAuth;

use App\Services\Oidc\PendingIdToken;
use Laravel\Passport\Http\Controllers\AccessTokenController;
use League\OAuth2\Server\AuthorizationServer;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\HttpFoundation\Response;

class TokenController extends AccessTokenController
{
    public function __construct(
        AuthorizationServer $server,
        private readonly PendingIdToken $pendingIdToken,
    ) {
        parent::__construct($server);
    }

    public function issueToken(ServerRequestInterface $psrRequest, PsrResponseInterface $psrResponse): Response
    {
        $response = parent::issueToken($psrRequest, $psrResponse);

        if ($response->getStatusCode() !== 200 || $this->pendingIdToken->token === null) {
            return $response;
        }

        $payload = json_decode($response->getContent(), true);
        $payload['id_token'] = $this->pendingIdToken->token;

        return response()->json($payload, $response->getStatusCode(), $response->headers->all());
    }
}
```

This is only reachable once the route from Task 8 is wired up — nothing to run standalone here.

- [ ] **Step 2: Commit**

```bash
git add app/Http/Controllers/OAuth/TokenController.php
git commit -m "🎇 Add token controller that attaches id_token to the response"
```

---

### Task 8: OAuth routes (authorize + token)

**Files:**
- Create: `routes/oauth.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Write the route file**

```php
<?php

use App\Http\Controllers\OAuth\TokenController;
use Illuminate\Support\Facades\Route;
use Laravel\Passport\Http\Controllers\AuthorizationController;

Route::get('/oauth/authorize', [AuthorizationController::class, 'authorize'])
    ->middleware('web')
    ->name('passport.authorizations.authorize');

Route::post('/oauth/token', [TokenController::class, 'issueToken'])
    ->middleware('throttle')
    ->name('passport.token');
```

We deliberately don't copy Passport's approve/deny/refresh/device/personal-access-token/client-management routes: approve/deny are only reachable when a client doesn't skip authorization, and we only register first-party clients (Task 3 makes them always skip it); the rest are for grant types and self-service flows this project explicitly doesn't support (see the design doc's "Out of scope" section).

- [ ] **Step 2: Require it from web.php**

```php
<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
    Route::livewire('admin/users', 'pages::admin.users')->name('admin.users');
    Route::livewire('sftp/password', 'pages::sftp.password')->name('sftp.password');
});

require __DIR__ . '/settings.php';
require __DIR__ . '/oauth.php';
```

- [ ] **Step 3: Verify routes are registered**

```bash
vendor/bin/sail artisan route:list --path=oauth
```

Expected: shows `GET /oauth/authorize` and `POST /oauth/token`, and does **not** list `passport.authorizations.approve` / `.deny` / `passport.token.refresh` (confirms `Passport::ignoreRoutes()` from Task 4 took effect and only our two routes exist).

- [ ] **Step 4: Commit**

```bash
git add routes/oauth.php routes/web.php
git commit -m "🎇 Register OAuth authorize and token routes"
```

---

### Task 9: End-to-end authorization code + PKCE flow test

**Files:**
- Test: `tests/Feature/Oidc/AuthorizationCodeFlowTest.php`

This is the test that proves Tasks 1–8 fit together: real HTTP requests through `/oauth/authorize` and `/oauth/token`, not Passport's `actingAs` shortcut.

- [ ] **Step 1: Write the test**

```php
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

    $authorizeResponse = $this->actingAs($user)->get('/oauth/authorize?' . http_build_query([
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

    $idToken = (new Parser(new JoseEncoder()))->parse($tokenResponse->json('id_token'));

    $publicKey = InMemory::plainText(file_get_contents(Passport::keyPath('oauth-public.key')));
    expect((new Validator())->validate($idToken, new SignedWith(new Sha256(), $publicKey)))->toBeTrue();

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

    $response = $this->actingAs($user)->get('/oauth/authorize?' . http_build_query([
        'client_id' => $client->getKey(),
        'redirect_uri' => 'https://third-party.test/callback',
        'response_type' => 'code',
        'scope' => 'openid',
        'state' => 'xyz',
        'code_challenge' => 'x',
        'code_challenge_method' => 'S256',
    ]));

    $response->assertOk();
    $response->assertDontSee('id_token');
});
```

The second test is a guardrail: it confirms `skipsAuthorization()` (Task 3) really is scoped to first-party clients and doesn't accidentally auto-approve everything. Since we didn't wire up an authorization view/route for the consent screen (Task 8 intentionally omits it — see spec's "Out of scope"), this request is expected to render Passport's default `AuthorizationViewResponse` rather than redirect; `assertOk()` combined with not finding a token in the response is enough to prove no code was silently issued.

- [ ] **Step 2: Run the tests**

```bash
vendor/bin/sail artisan test --compact tests/Feature/Oidc/AuthorizationCodeFlowTest.php
```

Expected: PASS (2 tests). If the first test fails at the `/oauth/token` step with a `redirect_uri` mismatch, double check the client's registered redirect URI exactly matches the one used in both requests (Passport is strict here).

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/Oidc/AuthorizationCodeFlowTest.php
git commit -m "🎇 Add end-to-end authorization code + PKCE flow test"
```

---

### Task 10: /oauth/userinfo endpoint

**Files:**
- Create: `app/Http/Controllers/OAuth/UserInfoController.php`
- Modify: `routes/oauth.php`
- Test: `tests/Feature/Oidc/UserInfoTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Models\Permission;
use App\Models\User;
use Laravel\Passport\Passport;

test('userinfo returns oidc claims for the token owner', function () {
    $user = User::factory()->create(['name' => 'Ada Lovelace', 'email' => 'ada@example.test']);
    $permission = Permission::create(['name' => 'admin', 'label' => 'Administrator']);
    $user->permissions()->attach($permission);

    Passport::actingAs($user, ['openid']);

    $response = $this->getJson('/oauth/userinfo');

    $response->assertOk()->assertJson([
        'sub' => (string) $user->id,
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.test',
        'email_verified' => false,
        'permissions' => ['admin'],
    ]);
});

test('userinfo rejects a token without the openid scope', function () {
    $user = User::factory()->create();

    Passport::actingAs($user, ['profile']);

    $this->getJson('/oauth/userinfo')->assertStatus(403);
});
```

- [ ] **Step 2: Run test to verify it fails**

```bash
vendor/bin/sail artisan test --compact tests/Feature/Oidc/UserInfoTest.php
```

Expected: FAIL with a 404 (route doesn't exist yet).

- [ ] **Step 3: Write the controller**

```php
<?php

namespace App\Http\Controllers\OAuth;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserInfoController
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless($user->tokenCan('openid'), 403);

        return response()->json([
            'sub' => (string) $user->getKey(),
            'name' => $user->name,
            'email' => $user->email,
            'email_verified' => $user->email_verified_at !== null,
            'permissions' => $user->permissions()->pluck('name')->values()->all(),
        ]);
    }
}
```

- [ ] **Step 4: Add the route**

Append to `routes/oauth.php`:

```php
use App\Http\Controllers\OAuth\UserInfoController;

Route::get('/oauth/userinfo', [UserInfoController::class, 'show'])
    ->middleware('auth:api')
    ->name('oidc.userinfo');
```

- [ ] **Step 5: Run test to verify it passes**

```bash
vendor/bin/sail artisan test --compact tests/Feature/Oidc/UserInfoTest.php
```

Expected: PASS (2 tests).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/OAuth/UserInfoController.php routes/oauth.php tests/Feature/Oidc/UserInfoTest.php
git commit -m "🎇 Add /oauth/userinfo endpoint"
```

---

### Task 11: Discovery document and JWKS endpoints

**Files:**
- Create: `app/Http/Controllers/OAuth/DiscoveryController.php`
- Create: `app/Http/Controllers/OAuth/JwksController.php`
- Modify: `routes/oauth.php`
- Test: `tests/Feature/Oidc/DiscoveryTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Services\Oidc\OidcKey;

test('discovery document exposes the expected endpoints', function () {
    $response = $this->getJson('/.well-known/openid-configuration');

    $response->assertOk()->assertJson([
        'issuer' => config('app.url'),
        'authorization_endpoint' => config('app.url') . '/oauth/authorize',
        'token_endpoint' => config('app.url') . '/oauth/token',
        'userinfo_endpoint' => config('app.url') . '/oauth/userinfo',
        'jwks_uri' => config('app.url') . '/oauth/jwks',
    ]);
});

test('jwks endpoint exposes the signing key', function () {
    $response = $this->getJson('/oauth/jwks');

    $response->assertOk()->assertJson([
        'keys' => [OidcKey::jwk()],
    ]);
});
```

- [ ] **Step 2: Run test to verify it fails**

```bash
vendor/bin/sail artisan test --compact tests/Feature/Oidc/DiscoveryTest.php
```

Expected: FAIL with 404s (routes don't exist yet).

- [ ] **Step 3: Write the controllers**

```php
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
```

```php
<?php

namespace App\Http\Controllers\OAuth;

use App\Services\Oidc\OidcKey;
use Illuminate\Http\JsonResponse;

class JwksController
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'keys' => [OidcKey::jwk()],
        ]);
    }
}
```

- [ ] **Step 4: Add the routes**

Append to `routes/oauth.php`:

```php
use App\Http\Controllers\OAuth\DiscoveryController;
use App\Http\Controllers\OAuth\JwksController;

Route::get('/oauth/jwks', JwksController::class)->name('oidc.jwks');

Route::get('/.well-known/openid-configuration', DiscoveryController::class)->name('oidc.discovery');
```

- [ ] **Step 5: Run test to verify it passes**

```bash
vendor/bin/sail artisan test --compact tests/Feature/Oidc/DiscoveryTest.php
```

Expected: PASS (2 tests).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/OAuth/DiscoveryController.php app/Http/Controllers/OAuth/JwksController.php routes/oauth.php tests/Feature/Oidc/DiscoveryTest.php
git commit -m "🎇 Add OIDC discovery document and JWKS endpoints"
```

---

### Task 12: Full suite check and Pint

**Files:** none (verification only)

- [ ] **Step 1: Run the full test suite**

```bash
vendor/bin/sail artisan test --compact
```

Expected: all tests pass, including the pre-existing suite (no regressions from the `api` guard or route changes).

- [ ] **Step 2: Run Pint on everything touched this branch**

```bash
vendor/bin/sail bin pint --dirty --format agent
```

Expected: no violations, or auto-fixed and re-verified.

- [ ] **Step 3: Commit if Pint made changes**

```bash
git add -A
git commit -m "🧵 Fix code style"
```

---

## After this plan: provisioning a real client

Registering an actual Istic service is a manual, per-integration step (by design — see spec's "Client management" section), not something this plan automates:

```bash
vendor/bin/sail artisan passport:client --public --name="Some Istic Service" --redirect_uri="https://service.example/oauth/callback"
```

Hand the printed Client ID to whoever configures that service's OIDC client, pointing it at:
- Authorization endpoint: `https://<alchemistic-host>/oauth/authorize`
- Token endpoint: `https://<alchemistic-host>/oauth/token`
- Discovery: `https://<alchemistic-host>/.well-known/openid-configuration`

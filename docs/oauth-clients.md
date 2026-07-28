# Configuring a client to use Alchemistic as an OIDC provider

Alchemistic acts as an OpenID Connect provider for **first-party Istic
services only** — there is no self-service client registration, and only the
Authorization Code + PKCE grant (plus the refresh tokens it issues) is
supported. See `docs/superpowers/specs/2026-07-23-oauth-provider-design.md`
for the full design rationale.

## 1. Register the client

Clients are registered manually via Artisan on the Alchemistic server — there
is no admin UI or API for this.

```bash
php artisan passport:client
```

- Answer `no` to "Would you like to make this client confidential?" for an
  SPA, mobile app, or any client that can't safely keep a secret (this
  enables PKCE). Answer `yes` for a confidential server-side app and store
  the generated **Client Secret** securely — it is only shown once.
- Give the real redirect URI(s) for the consuming app when prompted (e.g.
  `https://example.istichosting.co.uk/auth/callback`). Multiple URIs can be
  comma-separated.
- Decline the device authorization flow prompt (not supported here).

Do **not** pass a `user:` owner to `ClientRepository` when creating the
client — that's what makes Passport treat it as first-party, which is what
lets it skip the consent screen (`App\Models\Passport\Client::skipsAuthorization()`).
The plain `passport:client` command above never sets an owner, so this is
automatic.

Note the **Client ID** printed at the end; the consuming app needs it.

## 2. Endpoints

| Endpoint | URL |
|---|---|
| Issuer | `https://alchemistic.example/` (i.e. `config('app.url')`) |
| Discovery document | `GET /.well-known/openid-configuration` |
| Authorization | `GET /oauth/authorize` |
| Token | `POST /oauth/token` |
| UserInfo | `GET` or `POST /oauth/userinfo` |
| JWKS | `GET /oauth/jwks` |

Most OIDC client libraries only need the discovery URL and the client
ID/secret — point the library's issuer/discovery config at
`/.well-known/openid-configuration` and it will resolve the rest.

## 3. Authorization request

Redirect the user to `/oauth/authorize` with:

| Parameter | Value |
|---|---|
| `client_id` | the Client ID from step 1 |
| `redirect_uri` | one of the client's registered redirect URIs |
| `response_type` | `code` |
| `scope` | `openid` at minimum; add `profile` and/or `email` as needed |
| `state` | random, per-request, checked on return |
| `code_challenge` | S256 hash of a random `code_verifier`, base64url-encoded, no padding |
| `code_challenge_method` | `S256` |
| `nonce` | optional; if supplied it's echoed back verbatim in the `id_token` |

Because the client is first-party, the user goes straight from login to the
redirect — there is no consent screen to click through.

## 4. Token exchange

```bash
curl -X POST https://alchemistic.example/oauth/token \
  -d grant_type=authorization_code \
  -d client_id=<client-id> \
  -d client_secret=<client-secret> \  # confidential clients only
  -d redirect_uri=<redirect-uri> \
  -d code=<code-from-redirect> \
  -d code_verifier=<original-code-verifier>
```

Response:

```json
{
  "access_token": "...",
  "refresh_token": "...",
  "id_token": "...",
  "expires_in": 31536000
}
```

`id_token` is only present if `openid` was in the requested scope.

Refresh tokens are single-use: exchange one via `grant_type=refresh_token` +
`refresh_token=<token>`, and the old refresh token is revoked as soon as the
new pair is issued.

## 5. Claims

Both the `id_token` and `/oauth/userinfo` (called with the access token as a
Bearer token, requires the `openid` scope) return:

- `sub` — Alchemistic user id
- `name`, `email`, `email_verified`
- `permissions` — array of permission names (`$user->permissions()->pluck('name')`)
- `nonce` — only on the `id_token`, only if one was supplied at step 3

## 6. Verifying the id_token

The `id_token` is signed RS256. Fetch the public key either from
`/oauth/jwks` (standard JWKS format, keyed by `kid`) or via the discovery
document's `jwks_uri`. Any standard OIDC/JWT library can verify the
signature and decode the claims from this without further Alchemistic-specific
code.

## Grant types explicitly not supported

`client_credentials`, `password`, and `device_code` are all rejected by the
token endpoint (`unsupported_grant_type`), even though some are technically
wired up inside Passport/League's `AuthorizationServer`. If a client needs
machine-to-machine access rather than user SSO, this isn't the right
integration — talk to the Alchemistic maintainers.

# Alchemistic as an OAuth/OIDC Provider

## Purpose

Alchemistic (user and service management portal for Istic Hosting) will become an
OpenID Connect provider so that other first-party Istic services can offer
"Log in with Alchemistic" SSO instead of maintaining separate credentials.

Scope is intentionally narrow: a handful of known, trusted internal client
apps. No self-service client registration, no third-party/public OAuth
support, no granular per-scope consent.

## Architecture

- **Laravel Passport** provides the OAuth2 mechanics: client storage,
  authorization codes, access/refresh tokens, revocation, signing keys, and
  console tooling (`artisan passport:client`).
- A **thin custom OIDC layer** is added on top of Passport:
  - `id_token` (signed JWT) issued alongside the access token from the token
    endpoint.
  - `GET /oauth/userinfo` — standard OIDC userinfo endpoint.
  - `GET /.well-known/openid-configuration` — discovery document.
  - `GET /oauth/jwks` — Passport's existing public key endpoint, reused for
    `id_token` verification.
- Only the **Authorization Code + PKCE** grant is enabled. No client
  credentials grant, no password grant, no device code grant, no personal
  access tokens.
- Login itself is unchanged: unauthenticated users hitting `/oauth/authorize`
  go through the existing Fortify login (+ 2FA if enabled).
- Registered clients are marked **first-party/trusted**, so Passport's
  consent/approval screen is skipped — after login, the user is redirected
  straight back to the client app with an authorization code. This matches
  typical internal SSO UX (e.g. logging into a Google Workspace app).

## Data model

Passport's standard migrations are added, limited to what the Authorization
Code grant needs:

- `oauth_clients`
- `oauth_auth_codes`
- `oauth_access_tokens`
- `oauth_refresh_tokens`

`oauth_personal_access_clients` and `oauth_device_codes` are not needed and
are not migrated/enabled.

No changes to the existing `permissions` / `user_permissions` tables or the
`User::permissions()` relationship — it's reused as-is to populate the
`permissions` claim below.

## Client management

Clients are created via Passport's built-in `artisan passport:client` command
(or a small seeder for reproducible local/staging environments), one per
Istic service that wants SSO. Each gets its own `client_id` / `client_secret`
and a registered redirect URI. The resulting credentials are handed to
whoever configures that service — a one-time manual step per new
integration. No admin UI for client management is built.

## Scopes

A single `openid` scope is defined (plus conventional `profile` / `email`
scopes per the OIDC spec). There is no granular, permission-based scope
system — Alchemistic permissions travel as a claim (below), not as something
a user consents to per-scope.

## Endpoints

| Route | Source | Purpose |
|---|---|---|
| `GET /oauth/authorize` | Passport | Authorization endpoint. Login via Fortify if needed; auto-approved for first-party clients. |
| `POST /oauth/token` | Passport, extended | Token endpoint. Extended to also mint an `id_token`. |
| `GET /oauth/userinfo` | New | Returns OIDC claims for the user identified by the presented access token. |
| `GET /.well-known/openid-configuration` | New | Discovery document (issuer, endpoint URLs, supported scopes/claims/grant types/signing algorithms). |
| `GET /oauth/jwks` | Passport | Public key(s) used to verify `id_token` signatures. |

These routes sit outside the existing `auth`/`verified` web middleware group
— Passport's authorization endpoint handles the login/authentication
requirement itself.

## Claims

Both the `id_token` and the `/oauth/userinfo` response include:

- `sub` — user id
- `iss` — Alchemistic's issuer URL
- `aud` — client id (id_token only)
- `exp`, `iat`
- `name`
- `email`
- `email_verified`
- `permissions` — array of permission names, from
  `$user->permissions()->pluck('name')`

## Testing

Per project convention (Pest, feature-first):

- Feature test driving the full flow end-to-end: request to `/oauth/authorize`
  with PKCE params → login → auto-approval redirect → authorization code →
  `POST /oauth/token` → decode `id_token` and assert claims (including
  `permissions` for a user with permissions assigned).
- Feature test for `GET /oauth/userinfo` with a valid access token.
- Feature test for `GET /.well-known/openid-configuration` asserting the
  expected shape/URLs.

Uses Passport's testing helpers (`Passport::actingAs`, etc.) where they apply;
the full authorize→token round trip is exercised directly via HTTP for at
least one test to prove the real flow works, not just token-based
shortcuts.

## Out of scope (explicitly not building)

- Self-service client registration UI
- Per-client granular scopes / consent screen
- Client credentials, password, or device code grants
- Public/third-party client support
- Refresh token rotation policy changes beyond Passport defaults

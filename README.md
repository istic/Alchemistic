# Alchemistic

User and service management portal for Istic Hosting.

## Local Development

`docker compose up -d` starts everything:

| Service | Purpose |
|---|---|
| `application` | PHP-FPM base image running `artisan serve` (port 80) and `npm run dev` (Vite, port 5173) under supervisord |
| `redis` | Cache/session/queue backend |
| `mailpit` | Catches outbound mail — UI at `http://localhost:8025` |
| `cloudflared` | Cloudflare named tunnel exposing the app publicly over HTTPS (see below) |

The app is reachable at `http://localhost` once `application` is up. `docker/development/` builds the dev image; `docker/production/` is the separate image deployed to Firth (see the note at the top of `compose.yaml`) — any dependency/extension changes should be mirrored between the two Dockerfiles.

### Public HTTPS tunnel (Cloudflare)

The `cloudflared` service exposes the local stack on real HTTPS domains, needed for anything that requires a public callback URL (OAuth providers, Twitch/webhook testing, testing on a phone, etc):

| Hostname | Routes to |
|---|---|
| `alchem.istic.dev` | `application:80` (the app) |
| `vite-alchem.istic.dev` | `application:5173` (Vite dev server/HMR) |
| `ws-alchem.istic.dev` | `reverb:8080` (Reverb, currently commented out in `compose.yaml`) |

This uses a **named tunnel**, not ngrok or a quick/ephemeral tunnel — the three hostnames above are permanent, routed via DNS CNAMEs to a single tunnel (`docker/cloudflared/config.yml`) rather than a random URL that changes every restart. `vite.config.js` reads `CLOUDFLARE_VITE_HOSTNAME` from `.env` to point Vite's HMR client at the tunnel instead of `localhost` when it's set.

The tunnel's Cloudflare account cert and per-tunnel credentials live in `docker/cloudflared/data/` (gitignored, bind-mounted into the container) — `docker/cloudflared/config.yml` itself only references the tunnel ID and hostnames, so it's safe to commit. To set up your own tunnel against a different domain/account:

```sh
docker compose run --rm --no-deps --entrypoint cloudflared cloudflared tunnel login
docker compose run --rm --no-deps --entrypoint cloudflared cloudflared tunnel create <name>
docker compose run --rm --no-deps --entrypoint cloudflared cloudflared tunnel route dns <name> <hostname>
```

then update `docker/cloudflared/config.yml` with the new tunnel ID, credentials filename, and hostnames.

(ngrok was used previously but its free-tier browser-warning interstitial can't be bypassed for cross-origin asset requests like Vite's — see PR #40 for the full writeup.)

## GitHub Workflows

Three workflows run on this repository: `tests`, `linter`, and `Build and publish Docker image`.

### Repository Variables

Set these under **Settings → Secrets and variables → Variables**:

| Variable | Used by | Description |
|---|---|---|
| `PHP_VERSION` | `docker.yml`, `lint.yml` | PHP version to use (e.g. `8.4`) |
| `NODE_VERSION` | `docker.yml`, `tests.yml` | Node.js version to use (e.g. `22`) |

### Environments

The `tests` and `linter` workflows use the **Testing** environment. No environment-specific variables are required beyond the repository variables above.

### Releases

Releases are created via the **Create Release Tag** workflow (`workflow_dispatch`). It runs the test suite first and only creates a tag if tests pass. Creating a tag triggers the Docker build workflow, which builds and pushes the image to `ghcr.io`.

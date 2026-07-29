#!/bin/bash
set -e

# Detect if running in Docker
if [[ -f /.dockerenv ]] || [[ -n "${DOCKER}" ]]; then
    IN_DOCKER=true
    WORKDIR="/var/www/html"
    echo "[cloudflared] Running in Docker container"
else
    IN_DOCKER=false
    WORKDIR="."
    echo "[cloudflared] Running standalone"
fi

CF_LOG_DIR="${WORKDIR}/storage/logs/cloudflared"
mkdir -p "${CF_LOG_DIR}"

PIDS=()
cleanup() {
    if [[ ${#PIDS[@]} -gt 0 ]]; then
        kill "${PIDS[@]}" 2>/dev/null || true
    fi
}
trap cleanup EXIT HUP INT TERM

# Load environment variables
if [[ -f "${WORKDIR}/.env" ]]; then
    source "${WORKDIR}/.env"
fi

ENVFILE="${WORKDIR}/.env"

function is_gnu_sed() {
    sed --version >/dev/null 2>&1
}

SED="sed"
if ! is_gnu_sed; then
    if hash gsed 2>/dev/null; then
        SED="gsed"
    fi
fi

function update_env() {
    local key="$1"
    local value="$2"
    if grep -q "^${key}=" "$ENVFILE" 2>/dev/null; then
        $SED -i "s@^${key}=.*@${key}=${value}@" "$ENVFILE"
        echo "[cloudflared] Updated ${key} in .env"
    else
        echo "${key}=${value}" >> "$ENVFILE"
        echo "[cloudflared] Added ${key} to .env"
    fi
}

# Start a quick tunnel in the background and wait for its assigned URL to
# appear in the log. Quick tunnels (no Cloudflare account/token) don't offer
# a management API like ngrok's :4040, so the URL has to be scraped from the
# banner cloudflared prints to its own log on startup.
function start_tunnel() {
    local name="$1"
    local upstream="$2"
    local logfile="${CF_LOG_DIR}/${name}.log"

    echo "[cloudflared] Starting tunnel '${name}' -> ${upstream}"
    cloudflared tunnel --url "${upstream}" --loglevel info > "${logfile}" 2>&1 &
    local pid=$!
    PIDS+=("$pid")

    local url=""
    for _ in $(seq 1 30); do
        url=$(grep -o 'https://[a-z0-9-]*\.trycloudflare\.com' "${logfile}" 2>/dev/null | head -1)
        if [[ -n "$url" ]]; then
            break
        fi
        if ! kill -0 "$pid" 2>/dev/null; then
            echo "[cloudflared] ERROR: tunnel '${name}' exited before it produced a URL" >&2
            cat "${logfile}" >&2
            return 1
        fi
        sleep 1
    done

    if [[ -z "$url" ]]; then
        echo "[cloudflared] ERROR: timed out waiting for tunnel '${name}' URL" >&2
        cat "${logfile}" >&2
        return 1
    fi

    echo "[cloudflared] Tunnel '${name}' ready: ${url}"
    update_env "CLOUDFLARE_DELTA_${name^^}_URL" "${url#https://}"
}

if [[ -z "$APP_PORT" ]]; then
    export APP_PORT=80
fi

# If in Docker, wait for the main app to be ready
if [[ "$IN_DOCKER" == true ]]; then
    echo "[cloudflared] Waiting for application to be ready on http://application:${APP_PORT}..."
    until curl -sf "http://application:${APP_PORT}" > /dev/null 2>&1; do
        echo "[cloudflared] Waiting for app..."
        sleep 2
    done
    echo "[cloudflared] App is ready!"
fi

# Delete old backups and create new backup
find "${WORKDIR}" -maxdepth 1 -name ".env.*.bak" -cmin +5 -delete 2>/dev/null || true
cp "$ENVFILE" "${ENVFILE}.$(date +%s).bak"

start_tunnel "app" "http://application:${APP_PORT}" || echo "[cloudflared] WARNING: continuing without app tunnel"
start_tunnel "vite" "http://application:${VITE_PORT:-5173}" || echo "[cloudflared] WARNING: continuing without vite tunnel"
start_tunnel "ws" "http://reverb:${REVERB_SERVER_PORT:-8080}" || echo "[cloudflared] WARNING: continuing without ws tunnel"

echo "[cloudflared] All tunnels are active!"

wait

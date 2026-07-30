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

# Load environment variables
if [[ -f "${WORKDIR}/.env" ]]; then
    source "${WORKDIR}/.env"
fi

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

# Named tunnel: hostnames -> origins are fixed in docker/cloudflared/config.yml
# (created via `cloudflared tunnel create` + `tunnel route dns`), so there's
# no URL discovery to do here, unlike ngrok/quick tunnels.
echo "[cloudflared] Starting named tunnel..."
exec cloudflared tunnel --config "${WORKDIR}/docker/cloudflared/config.yml" run

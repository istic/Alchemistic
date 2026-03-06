#!/bin/bash
set -e

# Detect if running in Docker
if [[ -f /.dockerenv ]] || [[ -n "${DOCKER}" ]]; then
    IN_DOCKER=true
    WORKDIR="/var/www/html"
    echo "[ngrok] Running in Docker container"
else
    IN_DOCKER=false
    WORKDIR="."
    echo "[ngrok] Running standalone"
fi

trap "rm -f ${WORKDIR}/ngrok.generated.yaml ${WORKDIR}/.env.ngrok; exit 1" EXIT HUP INT TERM

# Load environment variables
if [[ -f "${WORKDIR}/.env" ]]; then
    source "${WORKDIR}/.env"
fi

SCRIPTNAME=$(basename "$0")
ENVFILE="${WORKDIR}/.env"

if $(hash ts 2>/dev/null); then
    TS="ts"
else
    TS="cat"
fi

function is_gnu_sed() {
    sed --version >/dev/null 2>&1
}

function update_env_with_ngrok_urls() {
    local logfile="${WORKDIR}/storage/logs/ngrok-update-env.log"

    # Setup logging
    if hash ts 2>/dev/null; then
        exec > >(ts '[%Y-%m-%d %H:%M:%S]' | tee -a "$logfile") 2>&1
    else
        exec > >(tee -a "$logfile") 2>&1
        echo "-------------------------------------"
        echo "[$(date '+%Y-%m-%d %H:%M:%S')] Updating .env with ngrok URLs"
        echo "-------------------------------------"
    fi

    # Determine which sed to use (GNU sed required for in-place editing)
    local SED="sed"
    if ! is_gnu_sed; then
        if hash gsed 2>/dev/null; then
            echo "Using GNU sed (gsed)"
            SED="gsed"
        else
            echo "Warning: Non-GNU sed detected, in-place editing may not work properly"
            if [[ "$IN_DOCKER" == false ]]; then
                echo "Please install GNU sed: brew install gnu-sed"
                return 1
            fi
        fi
    fi

    # Fetch ngrok URLs and parse them
    curl --silent --show-error http://127.0.0.1:4040/api/tunnels |
        jq -r '.tunnels[] | "NGROK_" + (.name | ascii_upcase) + "_URL=" + (.public_url | sub("^[a-z]+://"; ""; "i"))' \
            > "${WORKDIR}/.env.ngrok"

    if [[ ! -s "${WORKDIR}/.env.ngrok" ]]; then
        echo "ERROR: No tunnel URLs found"
        return 1
    fi

    echo "Found tunnel URLs:"
    cat "${WORKDIR}/.env.ngrok"

    # Delete old backups and create new backup
    find "${WORKDIR}" -name ".env.*.bak" -cmin +5 -delete 2>/dev/null || true
    cp "$ENVFILE" "${ENVFILE}.$(date +%s).bak"

    # Ensure .env exists
    if [[ ! -f $ENVFILE ]]; then
        echo "No $ENVFILE found, creating a new one."
        touch "$ENVFILE"
    fi

    # Update .env with ngrok URLs
    while IFS='=' read -r key value; do
        echo "Processing ${key}=${value}"
        if grep -q "^${key}=" "$ENVFILE"; then
            $SED -i "s@^${key}=.*@${key}=${value}@" "$ENVFILE"
            echo "Updated ${key} in .env"
        else
            echo "${key}=${value}" >> "$ENVFILE"
            echo "Added ${key} to .env"
        fi
    done < "${WORKDIR}/.env.ngrok"

    # Update public/hot if needed
    if grep -q "^NGROK_DELTA_VITE_URL=" "${WORKDIR}/.env.ngrok"; then
        local VITE_URL=$(grep "^NGROK_DELTA_VITE_URL=" "${WORKDIR}/.env.ngrok" | cut -d'=' -f2)
        if [[ -f "${WORKDIR}/public/hot" ]]; then
            echo "https://${VITE_URL}" > "${WORKDIR}/public/hot"
            echo "Updated public/hot with Vite URL"
        fi
    fi

    rm -f "${WORKDIR}/.env.ngrok"
    echo "Environment update complete!"
}

# If in Docker, wait for the main app to be ready
if [[ "$IN_DOCKER" == true ]]; then
    echo "[ngrok] Waiting for istic-manage.dev to be ready..."
    until curl -sf "http://istic-manage.dev:${APP_PORT:-80}" > /dev/null 2>&1; do
        echo "[ngrok] Waiting for app..."
        sleep 2
    done
    echo "[ngrok] App is ready!"
fi

# Generate ngrok config
echo "[ngrok] Generating ngrok configuration..."
cd "${WORKDIR}"
envsubst < ngrok.example.yaml > ngrok.generated.yaml

# Start ngrok in background if in Docker, foreground otherwise
if [[ "$IN_DOCKER" == true ]]; then
    echo "[ngrok] Starting ngrok tunnels..."
    ngrok start --all --config ./ngrok.generated.yaml --log=stdout &
    NGROK_PID=$!

    # Wait for ngrok API to be ready
    echo "[ngrok] Waiting for ngrok API..."
    sleep 5
    until curl -sf http://127.0.0.1:4040/api/tunnels > /dev/null 2>&1; do
        echo "[ngrok] Waiting for ngrok API..."
        sleep 2
    done

    echo "[ngrok] Ngrok is ready! Updating environment..."
    update_env_with_ngrok_urls

    echo "[ngrok] Ngrok tunnels are active!"
    echo "[ngrok] Dashboard available at http://localhost:4040"

    # Keep container running
    wait $NGROK_PID
else
    # Standalone mode - update env in background, run ngrok in foreground
    delayed_config() {
        sleep 2
        update_env_with_ngrok_urls | $TS >> "${WORKDIR}/storage/logs/${SCRIPTNAME}.log" 2>&1
    }

    delayed_config &
    ngrok start --all --config ./ngrok.generated.yaml
fi


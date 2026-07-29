# Ngrok Docker Integration

This project includes an integrated ngrok service in Docker Compose that automatically exposes your local development environment via ngrok tunnels and updates the `.env` file with the tunnel URLs.

## Prerequisites

1. **Ngrok Auth Token**: Sign up at [ngrok.com](https://ngrok.com) and get your auth token
2. **Environment Variables**: Set the following in your `.env` file:

```env
NGROK_AUTHTOKEN=your_ngrok_authtoken_here
NGROK_URL=                    # Leave empty for random URL or specify a custom domain
NGROK_WS_URL=                 # Leave empty for random URL
NGROK_VITE_URL=               # Leave empty for random URL
```

## How It Works

The ngrok service uses a unified script (`bin/start-ngrok.sh`) that works in both Docker and standalone modes:

**Docker Mode:**
1. Detects Docker environment (via `/.dockerenv` or `DOCKER=true` env var)
2. Waits for the main app (streamdelta.dev) to be ready
3. Generates ngrok configuration from `ngrok.example.yaml`
4. Starts three tunnels:
   - **delta_app**: Main Laravel app (port 80)
   - **delta_ws**: WebSocket/Reverb server (port 8080)
   - **delta_vite**: Vite dev server (port 5173)
5. Automatically updates `.env` with public tunnel URLs
6. Keeps running in foreground (container stays alive)

**Standalone Mode:**
1. Runs from project root directory
2. Updates environment variables in background
3. Runs ngrok in foreground with output to terminal

The script automatically:
- Updates `.env` with `NGROK_DELTA_APP_URL`, `NGROK_DELTA_WS_URL`, `NGROK_DELTA_VITE_URL`
- Updates `public/hot` with Vite tunnel URL for HMR
- Backs up `.env` before making changes
- Cleans up old backups (older than 5 minutes)

## Usage

### Starting All Services (Default - With Ngrok)

```bash
sail up -d
```

This starts all services including ngrok by default. The ngrok dashboard will be available at [http://localhost:4040](http://localhost:4040).

### Local-Only Mode (Without Ngrok)

If you don't need ngrok tunnels for local development:

**Option 1: Stop ngrok after starting**
```bash
sail up -d
sail stop ngrok
```

**Option 2: Start specific services only**
```bash
sail up -d streamdelta.dev redis mailpit
```

**Option 3: Scale ngrok to zero**
```bash
sail up -d --scale ngrok=0
```

### Viewing Ngrok Logs

```bash
sail logs -f ngrok
```

### Rebuilding the Ngrok Container

If you modify the Dockerfile or scripts:

```bash
sail build ngrok
sail up -d ngrok
```

## Tunnel Configuration

The ngrok tunnels are configured in `ngrok.example.yaml`:

```yaml
version: 3
agent:
  authtoken: $NGROK_AUTHTOKEN
endpoints:
  - name: delta_app
    url: $NGROK_URL
    upstream:
      url: 80

  - name: delta_ws
    url: ${NGROK_WS_URL}
    upstream:
      url: $REVERB_SERVER_PORT

  - name: delta_vite
    url: ${NGROK_VITE_URL}
    upstream:
      url: 5173
```

## Custom Domains

If you have a paid ngrok plan with custom domains, set them in `.env`:

```env
NGROK_URL=myapp.ngrok.io
NGROK_WS_URL=myapp-ws.ngrok.io
NGROK_VITE_URL=myapp-vite.ngrok.io
```

## Twitch Webhook Integration

Once ngrok starts, you can register your Twitch webhooks using the main app URL:
1. Access the admin panel at `https://<NGROK_DELTA_APP_URL>/admin`
2. Register EventSub webhooks pointing to `https://<NGROK_DELTA_APP_URL>/webhooks/twitch`

## Troubleshooting

### Ngrok container keeps restarting
- Check that your `NGROK_AUTHTOKEN` is valid
- Verify ngrok logs: `sail logs ngrok`
- Ensure the main app is running before ngrok starts

### .env not updating with URLs
- Check logs: `cat storage/logs/ngrok-update-env.log`
- Verify ngrok API is accessible: `curl http://localhost:4040/api/tunnels`
- Check file permissions on `.env`

### Tunnels not connecting
- Verify all required environment variables are set
- Check ngrok dashboard at [http://localhost:4040](http://localhost:4040)
- Ensure you're not exceeding ngrok free tier limits (4 tunnels max on free plan)

## Migration from Old Setup

The old separate `bin/ngrok-update-env.sh` script has been consolidated into `bin/start-ngrok.sh`. The unified script automatically detects whether it's running in Docker or standalone mode and adjusts its behavior accordingly.

**For standalone usage (without Docker):**
```bash
./bin/start-ngrok.sh
```

The script will run ngrok locally and update your `.env` file the same way it does in Docker mode.

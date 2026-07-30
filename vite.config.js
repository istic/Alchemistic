import { defineConfig, loadEnv } from "vite";
import laravel from "laravel-vite-plugin";
import tailwindcss from "@tailwindcss/vite";

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), "");
    const tunnelViteHost = env.CLOUDFLARE_VITE_HOSTNAME;

    if (tunnelViteHost) {
        console.log(`[vite] Using Cloudflare tunnel hostname for HMR: ${tunnelViteHost}`);
    } else {
        console.warn(
            "[vite] CLOUDFLARE_VITE_HOSTNAME is not set; falling back to localhost HMR. " +
                "If you're serving through the cloudflared tunnel, HMR will not work " +
                "until you set CLOUDFLARE_VITE_HOSTNAME in your .env.",
        );
    }

    return {
        plugins: [
            laravel({
                input: ["resources/css/app.css", "resources/js/app.js"],
                refresh: true,
            }),
            tailwindcss(),
        ],
        server: {
            host: "0.0.0.0",
            port: 5173,
            headers: {
                "Access-Control-Allow-Origin": "*",
            },
            origin: tunnelViteHost ? `https://${tunnelViteHost}` : undefined,
            hmr: tunnelViteHost
                ? { host: tunnelViteHost, protocol: "wss", clientPort: 443 }
                : { host: "localhost" },
            allowedHosts: tunnelViteHost ? [tunnelViteHost] : [],
        },
    };
});

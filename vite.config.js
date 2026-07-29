import { defineConfig, loadEnv } from "vite";
import laravel from "laravel-vite-plugin";
import tailwindcss from "@tailwindcss/vite";

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), "");
    const ngrokViteHost = env.NGROK_DELTA_VITE_URL;

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
            origin: ngrokViteHost ? `https://${ngrokViteHost}` : undefined,
            hmr: ngrokViteHost
                ? { host: ngrokViteHost, protocol: "wss", clientPort: 443 }
                : { host: "localhost" },
            allowedHosts: [".ngrok-free.app", ".ngrok.app", ".ngrok.io"],
        },
    };
});

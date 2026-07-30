import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import tailwindcss from "@tailwindcss/vite";
import { iconGenerationPlugin } from "./bin/icons/vite-plugin";

export default defineConfig({
    plugins: [
	iconGenerationPlugin(),
        laravel({
            input: ["resources/css/app.css", "resources/js/app.js",
				"resources/icons/apple-touch-icon.png",
				"resources/icons/favicon-96x96.png",
				"resources/icons/favicon.ico",
				"resources/icons/favicon.svg",
				"resources/icons/web-app-manifest-192x192.png",
				"resources/icons/web-app-manifest-512x512.png",
				"resources/icons/logo-standard.png",
				"resources/icons/logo-on-white.png",
            ],
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
        hmr: {
            host: "localhost",
        },
    },
});

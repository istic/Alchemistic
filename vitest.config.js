import { defineConfig } from "vitest/config";

export default defineConfig({
    test: {
        include: ["bin/icons/**/*.test.js"],
    },
});

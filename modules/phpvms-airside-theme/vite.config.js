import { existsSync, readdirSync } from "node:fs";
import { dirname, resolve } from "node:path";
import { fileURLToPath } from "node:url";
import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import tailwindcss from "@tailwindcss/vite";

const root = dirname(fileURLToPath(import.meta.url));

/**
 * Every `resources/js/islands/*.js` is its own entry, so adding an island
 * means adding a file, not editing this config. A page loads one with
 * `@airsideVite('resources/js/islands/<name>.js')`.
 */
const islandsDir = resolve(root, "resources/js/islands");
const islands = existsSync(islandsDir)
  ? readdirSync(islandsDir)
      .filter((file) => file.endsWith(".js"))
      .map((file) => `resources/js/islands/${file}`)
  : [];

export default defineConfig({
  // The build is served through the public storage link, not from /build.
  base: "/storage/themes/airside/build/",

  // maplibre constructs its worker as a module worker (see the root vite config).
  worker: { format: "es" },

  resolve: {
    alias: {
      // TallStackUI's stylesheet asks for `@tailwindcss/forms`, which this
      // repo does not install. The stub keeps that stylesheet importable;
      // airside styles form controls itself (resources/css/airside.css).
      // Delete the alias when the package is added.
      "@tailwindcss/forms": resolve(root, "resources/js/stubs/tailwind-forms.js"),
    },
  },

  plugins: [
    tailwindcss(),
    laravel({
      // Relative to this folder: the build goes to modules/phpvms-airside-theme/public/build
      // with its own hot file (public/hot), never the root public/hot.
      input: ["resources/css/styles.css", "resources/js/airside.js", ...islands],
      buildDirectory: "build",
      hotFile: "public/hot",
    }),
  ],

  server: { cors: true },
});

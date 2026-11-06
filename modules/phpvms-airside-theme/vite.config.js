import { existsSync, mkdirSync, readFileSync, readdirSync, writeFileSync } from "node:fs";
import { dirname, resolve } from "node:path";
import { fileURLToPath } from "node:url";
import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import tailwindcss from "@tailwindcss/vite";

const root = dirname(fileURLToPath(import.meta.url));

/**
 * Every `resources/js/islands/*.ts` is its own entry, so adding an island
 * means adding a file, not editing this config. A page loads one with
 * `@airsideVite('resources/js/islands/<name>.ts')`.
 */
const islandsDir = resolve(root, "resources/js/islands");
const islands = existsSync(islandsDir)
  ? readdirSync(islandsDir)
      .filter((file) => file.endsWith(".ts"))
      .map((file) => `resources/js/islands/${file}`)
  : [];

/**
 * Writes every Meteocons icon as a standalone `meteocons/<name>.svg` in the build
 * output. The icons are SMIL-animated, which only plays in an `<img>`, so they
 * cannot be inlined like the other icon sets. `x-vms::meteocon` points at these files.
 *
 * Icons: Meteocons by Bas Milius (MIT), via @iconify-json/meteocons.
 */
function meteocons() {
  const file = resolve(root, "node_modules/@iconify-json/meteocons/icons.json");

  /** @returns {[string, string][]} file name and SVG source of every icon */
  const icons = () => {
    if (!existsSync(file)) return [];

    const set = JSON.parse(readFileSync(file, "utf8"));

    return Object.entries(set.icons).map(([name, icon]) => {
      const left = icon.left ?? set.left ?? 0;
      const top = icon.top ?? set.top ?? 0;
      const width = icon.width ?? set.width ?? 16;
      const height = icon.height ?? set.height ?? 16;

      return [
        `meteocons/${name}.svg`,
        `<svg xmlns="http://www.w3.org/2000/svg" viewBox="${left} ${top} ${width} ${height}" width="${width}" height="${height}">${icon.body}</svg>`,
      ];
    });
  };

  return {
    name: "airside-meteocons",
    // The build writes them with the other assets.
    generateBundle() {
      for (const [fileName, source] of icons()) {
        this.emitFile({ type: "asset", fileName, source });
      }
    },
    // The dev server builds nothing, so write them where the build would.
    configureServer() {
      for (const [fileName, source] of icons()) {
        const target = resolve(root, "public/build", fileName);
        mkdirSync(dirname(target), { recursive: true });
        writeFileSync(target, source);
      }
    },
  };
}

export default defineConfig({
  // The build is served through the public storage link, not from /build.
  base: "/storage/themes/airside/build/",

  // maplibre constructs its worker as a module worker (the core vite config does the same).
  worker: { format: "es" },

  resolve: {
    alias: {
      // The theme owns its copies of the map and simbrief packages under resources/js/lib.
      "@airside": resolve(root, "resources/js"),
      // TallStackUI's stylesheet asks for `@tailwindcss/forms`, which this
      // repo does not install. The stub keeps that stylesheet importable;
      // airside styles form controls itself (resources/css/airside.css).
      // Delete the alias when the package is added.
      "@tailwindcss/forms": resolve(root, "resources/js/stubs/tailwind-forms.js"),
    },
  },

  plugins: [
    tailwindcss(),
    meteocons(),
    laravel({
      // Relative to this folder: the build goes to modules/phpvms-airside-theme/public/build
      // with its own hot file (public/hot), never the root public/hot.
      input: ["resources/css/styles.css", "resources/js/airside/app.ts", ...islands],
      buildDirectory: "build",
      hotFile: "public/hot",
    }),
  ],

  server: { cors: true },
});

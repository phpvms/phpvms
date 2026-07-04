import { defineConfig } from "vite";
import vue from "@vitejs/plugin-vue";
import tailwindcss from "@tailwindcss/vite";
import { phpTypescriptTransform } from "./dev/php-typescript-transform";
import { resolve, dirname } from "node:path";
import { fileURLToPath } from "node:url";
import { copyFileSync, mkdirSync, readdirSync, statSync, writeFileSync, unlinkSync } from "node:fs";

const __dirname = dirname(fileURLToPath(import.meta.url));

// The build SOURCE lives at resources/js/apps/frontend-ui/, but the runtime
// theme identity stays "skylight" (views/layouts/skylight, public/build/skylight,
// the Skylight facade) — the source dir and the theme name are decoupled.
const WORKSPACE_ROOT = __dirname;
const THEME_NAME = "skylight";
const DIST_DIR = resolve(WORKSPACE_ROOT, "dist");
// Walk up to the repo root: frontend-ui -> apps -> js -> resources -> repo.
const REPO_ROOT = resolve(WORKSPACE_ROOT, "..", "..", "..", "..");
// Laravel theme layout dir (published dist + manifest land here).
const LAYOUTS_DIR = resolve(REPO_ROOT, "resources", "views", "layouts", THEME_NAME);
// Web-served asset root: repo-root/public/build/<theme>/ (browser loads from here).
const PUBLIC_BUILD_DIR = resolve(REPO_ROOT, "public", "build", THEME_NAME);

// Single shared Vue: the browser-native ESM production build. Published verbatim
// to <asset base>/vendor/vue.js and pointed at by the shell's import-map so that
// the HOST app (whose `vue` import is externalized, see build.rollupOptions.external)
// AND any pre-built third-party ESM addon widget (`import 'vue'`, externalized at
// their build too) resolve the bare "vue" specifier to the SAME module URL. ESM
// caches by resolved URL, so one instance is guaranteed — effects + render share
// one runtime. The web-served path is `/build/<theme>/vendor/vue.js`.
const VENDOR_DIR_NAME = "vendor";
const VENDOR_VUE_FILE = "vue.js";
const VENDOR_VUE_SRC = resolve(
  WORKSPACE_ROOT,
  "node_modules",
  "vue",
  "dist",
  "vue.esm-browser.prod.js",
);

/** Copy the shared ESM Vue to <destRoot>/vendor/vue.js (stable, un-hashed URL). */
function copyVendorVue(destRoot: string) {
  const vendorDir = resolve(destRoot, VENDOR_DIR_NAME);
  mkdirSync(vendorDir, { recursive: true });
  const dest = resolve(vendorDir, VENDOR_VUE_FILE);
  copyFileSync(VENDOR_VUE_SRC, dest);
  return dest;
}

/**
 * Vite plugin that copies the built dist/ directory into the Laravel theme
 * layouts folder so the theme is discoverable and web-served.
 *
 * Also copies dist/.vite/manifest.json → layouts/manifest.json (flat) so
 * the theme descriptor `"manifest": "manifest.json"` resolves correctly.
 *
 * Only runs during `vite build` (not dev).
 */
function publishToLayouts(): import("vite").Plugin {
  return {
    name: "publish-to-layouts",
    apply: "build",
    closeBundle() {
      const copyDir = (src: string, dest: string) => {
        mkdirSync(dest, { recursive: true });
        for (const entry of readdirSync(src)) {
          const srcPath = resolve(src, entry);
          const destPath = resolve(dest, entry);
          if (statSync(srcPath).isDirectory()) {
            copyDir(srcPath, destPath);
          } else {
            copyFileSync(srcPath, destPath);
            console.log(`[publish-to-layouts] ${destPath}`);
          }
        }
      };
      // 1. Web-served assets → public/build/<theme>/ (the browser loads these).
      console.log(`[publish-to-layouts] Publishing dist/ → ${PUBLIC_BUILD_DIR}`);
      copyDir(DIST_DIR, PUBLIC_BUILD_DIR);

      // 1b. Shared single Vue at a stable, un-hashed URL. The shell's import-map
      //     maps the externalized bare "vue" specifier here for BOTH the host and
      //     third-party ESM addon widgets → one Vue instance across the tree.
      const vendorDest = copyVendorVue(PUBLIC_BUILD_DIR);
      console.log(`[publish-to-layouts] shared Vue → ${vendorDest}`);

      // 2. Flat manifest.json in BOTH places:
      //    - public/build/<theme>/manifest.json → the shell blade reads this to
      //      resolve hashed entry/css.
      //    - views/layouts/<theme>/manifest.json → satisfies theme.json's
      //      "manifest": "manifest.json" descriptor (theme discovery).
      const viteMeta = resolve(DIST_DIR, ".vite", "manifest.json");
      mkdirSync(LAYOUTS_DIR, { recursive: true });
      copyFileSync(viteMeta, resolve(PUBLIC_BUILD_DIR, "manifest.json"));
      copyFileSync(viteMeta, resolve(LAYOUTS_DIR, "manifest.json"));
      console.log(`[publish-to-layouts] manifest.json → ${PUBLIC_BUILD_DIR} + ${LAYOUTS_DIR}`);
      console.log("[publish-to-layouts] Done.");
    },
  };
}

/**
 * Dev-only: write a Vite `hot` file to public/build/<theme>/hot containing the
 * dev-server URL. The Inertia shell blade detects this file and loads modules
 * from the HMR server instead of the built manifest. Removed on shutdown.
 */
function writeHotFile(): import("vite").Plugin {
  const hotPath = resolve(PUBLIC_BUILD_DIR, "hot");
  const clean = () => {
    try {
      unlinkSync(hotPath);
    } catch {
      /* already gone */
    }
  };
  return {
    name: "write-hot-file",
    apply: "serve",
    configureServer(server) {
      const port = server.config.server.port ?? 5273;
      mkdirSync(PUBLIC_BUILD_DIR, { recursive: true });
      writeFileSync(hotPath, `http://localhost:${port}`);
      // Publish the shared Vue so the dev import-map URL resolves. NOTE: in dev the
      // host's own `import 'vue'` is served from Vite's optimized deps (Vite rewrites
      // bare specifiers before the import-map applies), so the import-map only feeds
      // third-party ESM addon widgets — which would then get a SECOND Vue instance in
      // dev. Dev fidelity for externalized ESM widgets is a known limitation; the
      // production build is the source of truth. See report.
      try {
        copyVendorVue(PUBLIC_BUILD_DIR);
      } catch {
        /* non-fatal in dev */
      }
      server.httpServer?.once("close", clean);
      process.once("SIGINT", clean);
      process.once("SIGTERM", clean);
      process.once("exit", clean);
    },
  };
}

export default defineConfig(({ command }) => ({
  root: WORKSPACE_ROOT,

  // Built assets are web-served under /build/<theme>/, so production chunk URLs
  // (incl. dynamic-import page chunks + preloaded CSS) must be prefixed with it.
  // Dev serves from the Vite dev-server root.
  base: command === "build" ? `/build/${THEME_NAME}/` : "/",

  resolve: {
    alias: {
      "@": resolve(WORKSPACE_ROOT, "apps/spa"),
    },
  },

  plugins: [
    vue(),
    tailwindcss(),
    // Dev-only: regenerate SPA types from PHP DTOs on change (no-op on build).
    phpTypescriptTransform({ repoRoot: REPO_ROOT }),
    publishToLayouts(),
    writeHotFile(),
  ],

  build: {
    outDir: DIST_DIR,
    emptyOutDir: true,
    // Generate Vite manifest so Laravel can map entry points to hashed assets.
    // This creates dist/.vite/manifest.json; we copy it flat as manifest.json
    // alongside assets so theme.json "manifest": "manifest.json" resolves.
    manifest: true,
    rollupOptions: {
      input: {
        spa: resolve(WORKSPACE_ROOT, "apps/spa/main.ts"),
      },
      // Externalize Vue: the host's (and its bundled deps') bare `import 'vue'`
      // statements are left as-is in the output instead of bundling a second copy.
      // At runtime the browser resolves "vue" via the shell's import-map to the
      // single shared vendor/vue.js — the same module third-party ESM widgets load.
      // One resolved URL ⇒ one ESM instance ⇒ one Vue runtime for host + addons.
      external: ["vue"],
    },
  },

  server: {
    // Port forwarded on host for the Sail container network.
    // The Laravel app (in Docker) reaches the host dev server via
    // host.docker.internal:5273 or localhost:5273 depending on the OS.
    // 5274 (not 5273): Sail forwards host 5273 via VITE_PORT, which would collide
    // with the host-run pnpm dev server. The hot file carries this URL, so the
    // shell blade adapts automatically.
    port: 5274,
    host: true, // bind 0.0.0.0 so Docker can reach the host via its gateway IP
    strictPort: true,

    // The hot file is written to the dist dir so Laravel's Vite integration
    // (or our custom hot-file reader) can detect the dev server.
    // URL: http://localhost:5273 (from the host / browser perspective)
    // URL from container: http://host.docker.internal:5273 (macOS Docker Desktop)
    hmr: {
      host: "localhost",
      port: 5274,
    },
  },
}));

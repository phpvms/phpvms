/**
 * Vitest config — test runner for RouteForge, rqb-core, the top-level admin
 * panel modules, the shared `resources/js/packages/*` workspace packages, and
 * the bin/ build scripts.
 *
 * `resources/js/apps/admin/routeforge/**` and `resources/js/rqb/**` are TS; the
 * third entry is the non-recursive top of `resources/js/admin`, where the
 * Filament panel's own `.js` modules live (rail-nav and friends). Everything
 * deeper in that tree — the legacy admin JS — stays outside the sweep.
 * happy-dom provides the minimal `window` + DOM globals that `lib/i18n.ts`,
 * rqb-core's sync helpers, and the panel modules' delegated `document`
 * listeners depend on without bringing in the full jsdom weight. The map
 * package's pure matrix/chunking modules need nothing more than that — no
 * WebGL context is created by these tests.
 *
 * `bin/*.test.mjs` covers Node-side build scripts (e.g.
 * measure-tailwind-safelist.mjs) that don't need happy-dom at all — they run
 * fine under it, just unused.
 *
 * `tsconfig.json` only includes `routeforge/**`, so the `.js` tests here add
 * nothing to `npm run typecheck`.
 *
 * No transform / plugin config — kept to plain `.test.ts`/`.test.js`/`.test.mjs`
 * files (no JSX), so no tree needs the preact plugin's esbuild config here.
 */
import { defineConfig } from "vitest/config";

export default defineConfig({
  test: {
    include: [
      "resources/js/apps/admin/routeforge/**/*.test.ts",
      "resources/js/rqb/**/*.test.ts",
      "resources/js/apps/admin/*.test.js",
      "resources/js/apps/admin/components/*.test.js",
      "resources/js/packages/map/**/*.test.ts",
      "bin/*.test.mjs",
    ],
    environment: "happy-dom",
    globals: false,
    reporters: "default",
  },
});

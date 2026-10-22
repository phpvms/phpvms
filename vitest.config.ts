/**
 * Vitest config — test runner for RouteForge, rqb-core, the top-level admin
 * panel modules, and the shared `resources/js/packages/*` workspace packages.
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
 * `tsconfig.json` only includes `routeforge/**`, so the `.js` tests here add
 * nothing to `npm run typecheck`.
 *
 * No transform / plugin config — kept to plain `.test.ts`/`.test.js` files (no
 * JSX), so no tree needs the preact plugin's esbuild config here.
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
    ],
    environment: "happy-dom",
    globals: false,
    reporters: "default",
  },
});

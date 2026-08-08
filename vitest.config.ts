/**
 * Vitest config — TS test runner for RouteForge and rqb-core.
 *
 * Scoped to `resources/js/admin/routeforge/**` and `resources/js/rqb/**` so
 * the legacy admin JS tree stays outside the type/test sweep. happy-dom
 * provides the minimal `window` + DOM globals that `lib/i18n.ts` and
 * rqb-core's sync helpers depend on without bringing in the full jsdom
 * weight.
 *
 * No transform / plugin config — kept to plain `.test.ts` files (no JSX), so
 * neither tree needs the preact plugin's esbuild config here.
 */
import { defineConfig } from "vitest/config";

export default defineConfig({
  test: {
    include: ["resources/js/admin/routeforge/**/*.test.ts", "resources/js/rqb/**/*.test.ts"],
    environment: "happy-dom",
    globals: false,
    reporters: "default",
  },
});

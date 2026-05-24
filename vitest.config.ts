/**
 * Vitest config — RouteForge TS test runner.
 *
 * Scoped to `resources/js/admin/routeforge/**` so the legacy admin JS tree
 * stays outside the type/test sweep. happy-dom provides the minimal `window`
 * + DOM globals that `lib/i18n.ts` (and any future client-only modules)
 * depend on without bringing in the full jsdom weight.
 *
 * No transform / plugin config — Vitest reuses vite.config.js when no
 * vitest.config is present, but here we want a narrower include scope.
 */
import { defineConfig } from "vitest/config";

export default defineConfig({
  test: {
    include: ["resources/js/admin/routeforge/**/*.test.ts"],
    environment: "happy-dom",
    globals: false,
    reporters: "default",
  },
});

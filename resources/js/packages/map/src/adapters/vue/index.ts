/**
 * The Vue adapter (design.md D1, tasks.md 7.1). Composables, not `.vue`
 * single-file components: this package's own tooling (root `vitest.config.ts`,
 * the bespoke build/lint checks used throughout this package) has no Vue
 * SFC pipeline, so an SFC here could not be typechecked, linted, or unit
 * tested from within this package — only verified by how fe-vue's OWN
 * `vite.config.ts` (which already has `vue()`) happens to build it, which
 * was not independently confirmed. Composables need no SFC compiler at all
 * and are fully covered by this package's existing test setup; an Inertia
 * page wraps them in its own thin component exactly as `useSimBriefAttempt.ts`
 * (`resources/js/apps/fe-vue/src/components/simbrief/`) already does for a
 * different composable. Flagged as a deliberate choice, not a silent gap —
 * say so if an actual `<MapView>` SFC is wanted instead.
 */
export { useMap } from "./useMap.ts";
export type { UseMapReturn } from "./useMap.ts";
export { useRoute } from "./useRoute.ts";

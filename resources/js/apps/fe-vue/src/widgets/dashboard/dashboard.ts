import type { Component } from "vue";
import RouteWidget from "./RouteWidget.vue";

/**
 * Resolver map: catalog `component` NAME → Vue component. Kept out of the
 * headless catalog so the catalog stays serializable.
 *
 * Only `route` (RouteWidget) stays bundled here — it imports @/shared/lib/useGlobe
 * + @/shared/lib/geo, which are core-internal and can't ride the ESM addon path.
 *
 * `PvActivityFeed` registers in the APP layer (PvApp.vue) so the dashboard slice
 * doesn't import a sibling widget slice. Other widgets moved to the
 * `phpvms/phpvms-dashboard` addon as pre-built ESM `module` widgets, resolving
 * via runtime import (see src/widgets/dashboard/resolve.ts) rather than this map.
 */
export const dashboardWidgets: Record<string, Component> = {
  RouteWidget,
};

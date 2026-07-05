import type { Component } from "vue";
import RouteWidget from "./RouteWidget.vue";
import PvActivityFeed from "@/widgets/activity/PvActivityFeed.vue";

/**
 * Resolver map: catalog `component` NAME → Vue component. Kept out of the
 * headless catalog so the catalog stays serializable. Addons that register a
 * WidgetDef also add their component here (or via a future dynamic import).
 *
 * Bundled first-party widgets stay here: `route` (imports core globe/geo) and
 * `activity` (the VA-wide feed, fetches a core endpoint). The KPI/rank/
 * last-flight/weather widgets moved to the `phpvms/phpvms-dashboard` addon as
 * pre-built ESM `module` widgets, so they resolve via runtime import (see
 * components/widgets/resolve.ts) rather than this map.
 */
export const dashboardWidgets: Record<string, Component> = {
  RouteWidget,
  PvActivityFeed,
};

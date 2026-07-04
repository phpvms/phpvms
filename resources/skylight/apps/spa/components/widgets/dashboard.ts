import type { Component } from "vue";
import RouteWidget from "./RouteWidget.vue";

/**
 * Resolver map: catalog `component` NAME → Vue component. Kept out of the
 * headless catalog so the catalog stays serializable. Addons that register a
 * WidgetDef also add their component here (or via a future dynamic import).
 *
 * Only `route` remains bundled — the KPI/rank/last-flight/weather widgets moved
 * to the `phpvms/phpvms-dashboard` addon as pre-built ESM `module` widgets, so
 * they resolve via runtime import (see components/widgets/resolve.ts) rather
 * than this map.
 */
export const dashboardWidgets: Record<string, Component> = {
  RouteWidget,
};

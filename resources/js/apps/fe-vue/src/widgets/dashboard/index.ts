import type { ComponentResolver } from "@/shared/lib/registry";

/**
 * Public-API barrel for the dashboard widget slice — exposes the composed
 * resolver maps (`dashboardWidgets`, `widgetComponents`). Other slice modules
 * with a distinct concern (`catalog`, `resolve`, `useDashboardLayout`,
 * `WidgetFrame`) are imported by their concrete path where needed; only the
 * resolver maps route through this index. Within the slice, use relative imports.
 */

/** Bundled widget component resolver — RouteWidget + other first-party widgets. */
export { dashboardWidgets } from "./dashboard";

/** App-layer hook to inject cross-slice widget components into the resolver. */
export { registerDashboardWidget } from "./dashboard";

/**
 * Concrete slot component resolver: maps registry `component` NAMES to Vue
 * components. Kept separate from the headless registry so the registry stays
 * serializable. Provided at the app root by PvApp.
 *
 * Empty by default — no first-party slot components are bundled (weather moved
 * to the phpvms/phpvms-dashboard addon). Addon slot components resolve via
 * runtime ESM `module` import, not this map.
 */
export const widgetComponents: ComponentResolver = {};

import type { ComponentResolver } from "@/shared/lib/registry";

/**
 * Public-API barrel for the dashboard widget slice.
 * All external importers (PvApp, pages) must use this index — never the
 * sub-modules directly. Within the slice, use relative imports.
 */

/** Bundled widget component resolver — RouteWidget + other first-party widgets. */
export { dashboardWidgets } from "./dashboard";

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

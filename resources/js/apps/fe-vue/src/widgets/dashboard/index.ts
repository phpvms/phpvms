import type { ComponentResolver } from "@/shared/lib/registry";

/**
 * Concrete component resolver: maps registry `component` NAMES to Vue
 * components. Kept separate from the headless registry so the registry stays
 * serializable. Provided at the app root by PvApp.
 *
 * Empty by default — no first-party slot components are bundled (weather moved
 * to the phpvms/phpvms-dashboard addon). Addon slot components resolve via
 * runtime ESM `module` import, not this map.
 */
export const widgetComponents: ComponentResolver = {};

import { registerDashboardWidget } from "@/widgets/dashboard";
import PvActivityFeed from "@/widgets/activity/PvActivityFeed.vue";

/**
 * App-layer widget wiring. The dashboard slice's resolver map (`dashboardWidgets`)
 * bundles only its own first-party widgets; cross-slice widgets are registered
 * HERE, at the app layer, so the dashboard slice never imports a sibling widget
 * slice (FSD: a layer imports only DOWNWARD, siblings never each other).
 *
 * Called once at app init (PvApp setup) before any dashboard page resolves a
 * widget. Idempotent — safe to call more than once (e.g. from a test).
 */
export function registerAppWidgets(): void {
  registerDashboardWidget("PvActivityFeed", PvActivityFeed);
}

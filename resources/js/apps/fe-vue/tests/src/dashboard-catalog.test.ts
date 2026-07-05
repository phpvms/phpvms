import { describe, it, expect } from "vitest";
import { registerAppWidgets } from "@/app/register-widgets";
import { getCatalog } from "@/widgets/dashboard/catalog";
import { resolveWidget } from "@/widgets/dashboard/resolve";

/**
 * Catalog ↔ resolver integrity. Every FIRST-PARTY bundled widget in the catalog
 * (a `component` name, no runtime `module` URL) must resolve to a REAL component
 * — not the fail-visible "WidgetResolveError" box. This guards the exact class
 * of bug where a widget's component lives in a different slice and its resolver
 * registration is missed (e.g. `PvActivityFeed`, registered at the app layer via
 * registerAppWidgets — see src/app/register-widgets.ts). vue-tsc + the build
 * can't catch a missing NAME→component mapping; only this can.
 *
 * NOTE: resolveWidget() returns the component reference without mounting it, so
 * this stays clear of RouteWidget's maplibre-gl init (which needs a real GL ctx).
 */
describe("dashboard catalog ↔ resolver integrity", () => {
  // Simulate app init so cross-slice (app-registered) widgets are present.
  registerAppWidgets();

  const bundled = getCatalog().filter((w) => w.component && !w.module && w.kind !== "blade");

  it("has bundled widgets to check", () => {
    expect(bundled.length).toBeGreaterThan(0);
  });

  it.each(bundled.map((w) => [w.id, w]))(
    "%s resolves to a real component (not the error box)",
    (_id, def) => {
      const { component } = resolveWidget(def);
      // The error box is a named inline component; a real widget is anything else.
      expect((component as { name?: string }).name).not.toBe("WidgetResolveError");
    },
  );
});

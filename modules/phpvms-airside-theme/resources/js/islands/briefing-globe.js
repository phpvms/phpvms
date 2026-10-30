import {
  createMap,
  createRoute,
  createRouteWaypoints,
  frameToRoute,
  LEG_COLORS,
} from "@phpvms/map";
import { routeGeometry } from "./ofp/route-points.js";

/**
 * The route globe on the OFP briefing page (skylight's BriefingRouteGlobe):
 * the navlog's fixes split into SID, enroute and STAR, a great circle between
 * the airports when there is no navlog, and an empty state when neither can be
 * drawn. A Tier 0 line, no terrain.
 *
 * Mounts on `[data-airside-island="briefing-globe"]`; the data is the child
 * `<script type="application/json">`: `{ map, departure, arrival, plannedFixes }`.
 * The airport ends are drawn as labelled dots (the Vue version uses DOM markers,
 * which would need a direct maplibre-gl dependency in the theme).
 */
const NAME = "briefing-globe";
const cleanups = new Set();

async function loaded(map) {
  if (map.loaded()) return;
  await map.once("load");
}

function mountIsland(root) {
  const data = JSON.parse(
    root.querySelector('script[type="application/json"]')?.textContent ?? "null",
  );
  const mapElement = root.querySelector('[data-role="map"]');
  const empty = root.querySelector('[data-role="empty"]');
  if (!data || !mapElement) return () => {};

  const geometry = routeGeometry(data);
  if (geometry.points.length === 0) {
    mapElement.hidden = true;
    if (empty) empty.hidden = false;

    return () => {};
  }

  let disposed = false;
  let destroy = null;
  const layers = [];

  void (async () => {
    const theme = document.documentElement.classList.contains("dark") ? "dark" : "light";
    const result = await createMap(mapElement, { config: data.map, theme, terrain: false });

    if (!result.ok) {
      mapElement.hidden = true;
      if (empty) {
        empty.textContent = "Route map unavailable in this browser.";
        empty.hidden = false;
      }

      return;
    }

    if (disposed) {
      result.destroy();

      return;
    }

    destroy = result.destroy;
    const { map } = result;
    await loaded(map);
    if (disposed) return;

    const line = await createRoute(map, 0, { phaseColors: LEG_COLORS, id: "briefing-route" });
    line.setData(geometry.points);

    const waypoints = createRouteWaypoints(map, { id: "briefing-waypoints" });
    waypoints.setData(geometry.waypoints);

    const airports = createRouteWaypoints(map, { id: "briefing-airports", pointRadius: 6 });
    airports.setData(geometry.airports);

    layers.push(line, waypoints, airports);
    frameToRoute(map, geometry.points, { pitch: 30 });
  })();

  return () => {
    disposed = true;
    // Layers first, while the map still has its style; then the map.
    for (const layer of layers.splice(0)) layer.dispose();
    destroy?.();
    destroy = null;
  };
}

function mountAll() {
  for (const root of document.querySelectorAll(`[data-airside-island="${NAME}"]`)) {
    if (root.dataset.mounted === "true") continue;
    root.dataset.mounted = "true";
    cleanups.add(mountIsland(root));
  }
}

function unmountAll() {
  for (const cleanup of cleanups) cleanup();
  cleanups.clear();
}

document.addEventListener("DOMContentLoaded", mountAll);
document.addEventListener("livewire:navigated", mountAll);
document.addEventListener("livewire:navigating", unmountAll);

if (document.readyState !== "loading") mountAll();

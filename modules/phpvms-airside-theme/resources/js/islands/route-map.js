import {
  CARTO_DARK_MATTER_URL,
  CARTO_VOYAGER_URL,
  createMap,
  createRoute,
  createRouteWaypoints,
  frameToRoute,
} from "@phpvms/map";
import { greatCircle, readout } from "./flights/geo.js";

/**
 * The nav display route map (skylight's NavDisplay): an EFIS-style header, a
 * globe with the great circle between two airports, and a track / distance /
 * ETE / level readout. A Tier 0 line, no terrain.
 *
 * Mounts on `[data-airside-island="route-map"]`. The data is the child
 * `<script type="application/json">`:
 *
 *   { "from": { "icao", "lat", "lon" }, "to": { "icao", "lat", "lon" } | null,
 *     "cruiseLevel": int | null, "height": int | null, "map": MapConfig | null }
 *
 * `map` is optional; without it the map package's default basemaps are used.
 * The island draws its own header, map and readout, so a page only needs the
 * wrapper and the script. Other pages may emit that markup and rely on this file.
 */
const NAME = "route-map";
const DEFAULT_HEIGHT = 360;
const FALLBACK_CONFIG = {
  basemapLight: CARTO_VOYAGER_URL,
  basemapDark: CARTO_DARK_MATTER_URL,
  customStyleUrl: null,
  customStyleApiKey: null,
  layers: [],
};
const cleanups = new Map();

async function loaded(map) {
  if (map.loaded()) return;
  await map.once("load");
}

function element(tag, className, text) {
  const node = document.createElement(tag);
  if (className) node.className = className;
  if (text) node.textContent = text;

  return node;
}

function readData(root) {
  try {
    return JSON.parse(root.querySelector('script[type="application/json"]')?.textContent ?? "null");
  } catch {
    return null;
  }
}

function hasPoint(point) {
  return point && typeof point.lat === "number" && typeof point.lon === "number";
}

function mountIsland(root) {
  const data = readData(root);
  if (!data || !hasPoint(data.from)) return () => {};

  const from = [data.from.lon, data.from.lat];
  const to = hasPoint(data.to) ? [data.to.lon, data.to.lat] : null;
  const height = Number.isFinite(data.height) ? data.height : DEFAULT_HEIGHT;

  const header = element("div", "vms-route-map__head");
  header.append(
    element(
      "span",
      "vms-route-map__title",
      "ND · MAPLIBRE · GLOBE",
    ),
    element(
      "span",
      "vms-route-map__route",
      to && data.to.icao ? `${data.from.icao} ▸ ${data.to.icao}` : (data.from.icao ?? ""),
    ),
  );

  const mapElement = element("div", "vms-route-map__map");
  mapElement.dataset.role = "map";
  mapElement.style.height = `${height}px`;

  const status = element("div", "vms-route-map__readout");
  status.dataset.role = "readout";
  readout(from, to, data.cruiseLevel).forEach((text, index) => {
    status.append(element("span", index === 0 ? "vms-route-map__track" : "", text));
  });

  const frame = element("div", "vms-route-map");
  frame.setAttribute("role", "img");
  frame.setAttribute(
    "aria-label",
    `Nav display, route ${data.from.icao ?? "unknown"} to ${data.to?.icao ?? "unknown"}`,
  );
  // Without a destination there is no track or distance to read out.
  frame.append(header, mapElement);
  if (to) frame.append(status);

  // Keep the data script, replace everything else.
  const script = root.querySelector('script[type="application/json"]');
  root.replaceChildren(frame);
  if (script) root.append(script);

  let disposed = false;
  let destroy = null;
  const layers = [];

  void (async () => {
    const theme = document.documentElement.classList.contains("dark") ? "dark" : "light";
    const config = data.map ?? FALLBACK_CONFIG;
    const result = await createMap(mapElement, { config, theme, terrain: false });

    if (!result.ok) {
      mapElement.textContent = "Route map unavailable in this browser.";
      mapElement.className = "vms-route-map__map vms-route-map__map--empty";
      mapElement.style.height = "auto";
      mapElement.style.minHeight = "180px";

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

    const points = to
      ? greatCircle(from, to).map(([lon, lat]) => ({ lat, lon, altitude: 0 }))
      : [{ lat: from[1], lon: from[0], altitude: 0 }];

    if (to) {
      const line = await createRoute(map, 0, { id: "route-map-line" });
      line.setData(points);
      layers.push(line);
    }

    const airports = createRouteWaypoints(map, { id: "route-map-airports", pointRadius: 6 });
    airports.setData(
      [
        { lat: from[1], lon: from[0], ident: data.from.icao ?? null, kind: "sid" },
        to ? { lat: to[1], lon: to[0], ident: data.to.icao ?? null, kind: "star" } : null,
      ].filter(Boolean),
    );
    layers.push(airports);

    frameToRoute(map, points, { pitch: 0 });
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
    cleanups.set(root, mountIsland(root));
  }
}

function unmountAll() {
  for (const [root, cleanup] of cleanups) {
    cleanup();
    delete root.dataset.mounted;
  }
  cleanups.clear();
}

document.addEventListener("DOMContentLoaded", mountAll);
document.addEventListener("livewire:navigated", mountAll);
document.addEventListener("livewire:navigating", unmountAll);

// The basemap follows the light / dark mode, so a mode change redraws the map.
let darkMode = document.documentElement.classList.contains("dark");
new MutationObserver(() => {
  const dark = document.documentElement.classList.contains("dark");
  if (dark === darkMode) return;
  darkMode = dark;
  unmountAll();
  mountAll();
}).observe(document.documentElement, { attributes: true, attributeFilter: ["class"] });

if (document.readyState !== "loading") mountAll();

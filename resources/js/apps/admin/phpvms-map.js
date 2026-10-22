/**
 * Local re-export of `@phpvms/map`'s imperative adapter, purely so
 * `app.js`'s `import.meta.glob(...)` call stays a single, SHORT expression —
 * `import.meta.glob("../../packages/map/src/adapters/imperative.ts")[...]()`
 * exceeds the line length that keeps the glob pattern and its object-key
 * lookup argument identical after formatting, and a wrapped call breaks
 * Vite's glob transform (see the "NOTE" in `app.js`, right below the
 * dashboard chunk's own loader). A short local path avoids the problem
 * entirely, the same way `./maps/index.js` does for the Leaflet chunk.
 *
 * Also owns the fetch + DTO-to-adapter-shape glue for the two admin map
 * surfaces (tasks.md 6.2/6.3): the adapter's own functions take rendering
 * options directly (`flown`, `planned`, `flights`), not a PIREP id or "give
 * me the live list" — something has to call `GET /api/map/pirep/{id}` and
 * `GET /api/map/live` and reshape the response. That something lives here,
 * inside the same lazily-loaded chunk, rather than duplicated per blade.
 */
import { renderLiveMap, renderPirepMap } from "../../packages/map/src/adapters/imperative.ts";

/** `RoutePoint.altitude` is metres; the map API reports feet. */
const FEET_TO_METRES = 0.3048;

/**
 * `GET api/map/live` rows -> `LiveFlightPoint[]` (design.md D10: the client
 * derives its marker source from the lean index with a `map()`, no
 * duplicate GeoJSON in the payload). Exported on its own so the live map's
 * poll loop (components/admin/live-map.blade.php) can reuse the exact same
 * transform the initial render uses, via `window.phpvms.map.fetch_live_flights`.
 */
export async function fetchLiveFlights() {
  const response = await fetch("/api/map/live");
  if (!response.ok) return [];

  /** @type {Array<{pirepId: string, position: {lat: number, lon: number}}>} */
  const rows = await response.json();

  return rows.map((row) => ({
    pirepId: row.pirepId,
    lat: row.position.lat,
    lon: row.position.lon,
  }));
}

/**
 * Fetch `MapPirepDetailData` for one PIREP and render it (Tier 1, at
 * altitude). `fallback` is passed through from the caller rather than built
 * here — the blade already has the departure/arrival ICAOs server-side
 * (they're printed in the route bar above the map), so there is no reason
 * to wait on this fetch just to build the D16 static-fallback text.
 */
export async function renderPirepMapFromApi(el, { pirepId, config, theme, fallback }) {
  const response = await fetch(`/api/map/pirep/${encodeURIComponent(pirepId)}`);
  if (!response.ok) return undefined;

  const detail = await response.json();

  // MapTrackPointData.altitude is nullable (a malformed ACARS sample); the
  // at-altitude renderer needs a number for every point, so a null-altitude
  // sample is dropped rather than coerced to 0, which would draw the route
  // diving to the ground.
  const flown = (detail.flown?.points ?? [])
    .filter((point) => point.altitude !== null)
    .map((point) => ({
      lat: point.lat,
      lon: point.lon,
      // `MapTrackPointData.altitude` is the raw ACARS `altitude_msl` — FEET.
      // `RoutePoint.altitude` is METRES (packages/map/src/types.ts). Without
      // this a 33,000ft point drew at 33,000 METRES, ~3.28x too high. The SPA
      // has always converted (LiveMapCanvas.vue); this surface did not, so the
      // same track rendered differently in admin and skylight.
      altitude: point.altitude * FEET_TO_METRES,
      phase: point.phase ?? undefined,
    }));

  const planned = (detail.planned?.fixes ?? []).map((fix) => ({
    ident: fix.ident,
    lat: fix.lat,
    lon: fix.lon,
    altitudeFt: fix.altitudeFt,
    viaAirway: fix.viaAirway,
  }));

  return renderPirepMap(el, {
    config,
    theme,
    fallback,
    flown,
    planned,
    fallbackAltitudeFt: detail.planned.fallbackAltitudeFt,
  });
}

/**
 * Initial render for the live-flights overview (Tier 0 — see design.md D5,
 * D13). Subsequent polls go through `fetchLiveFlights()` +
 * `handle.setFlights()` directly; this only covers the first paint.
 */
export async function renderLiveMapFromApi(el, { config, theme }) {
  const flights = await fetchLiveFlights();

  return renderLiveMap(el, { config, theme, flights });
}

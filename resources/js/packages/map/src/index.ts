/**
 * @phpvms/map — the shared maplibre-gl map core (proposal.md, design.md).
 *
 * Framework-agnostic TypeScript, consumed by the Filament admin panel through
 * an imperative adapter and by the Inertia frontend through Vue components
 * (design.md D1). No map rendering logic should live in
 * `resources/js/apps/admin` or `resources/js/apps/fe-vue` — those get thin
 * glue only.
 *
 * Rendering tiers (design.md D13): Tier 0 (`tier0-line.ts`) is an ordinary
 * maplibre `line` layer with NO three.js in its module graph — safe to import
 * eagerly. Tier 1 (`route-line.ts`) is the at-altitude `Line2` renderer
 * (tasks.md 2.0's verdict) and is loaded ONLY via the dynamic `import()`
 * below, so a page that never asks for Tier 1 never fetches three.js
 * (tasks.md 2b.2/2b.3 assert this split at build time).
 */

import type { Map as MapLibreMapType } from "maplibre-gl";
import { createTier0RouteLine } from "./tier0-line.ts";
import type { RoutePoint } from "./types.ts";

export type {
  MapConfig,
  MapCreateOptions,
  MapLayerConfig,
  MapTheme,
  MapTier,
  PlannedFix,
  RoutePoint,
  ScenePosition,
} from "./types.ts";

// Tier 0 is re-exported directly — it is exactly as cheap to import as this module itself.
export { createTier0RouteLine, groupByPhaseRuns } from "./tier0-line.ts";
export type { PhaseColorMap, Tier0RouteLine, Tier0RouteLineOptions } from "./tier0-line.ts";

// `createMap` and everything phase 3 built on top of it (`style.ts`, `layers.ts`, `attribution.ts`,
// `context-budget.ts`, `browser.ts`, `fallback.ts`) import no three.js anywhere in their own graph,
// so re-exporting them here does not affect the Tier 0/Tier 1 split `tiering.build.test.ts` asserts.
export { createMap } from "./base-map.ts";
export type { CreateMapHandle, CreateMapOptions, CreateMapResult } from "./base-map.ts";
export { boundsFor, frameToRoute, onUserTakesControl } from "./camera.ts";
export type { FramePoint, FrameOptions } from "./camera.ts";
export {
  CARTO_DARK_MATTER_URL,
  CARTO_VOYAGER_URL,
  ESRI_SENTINEL,
  MAPTERHORN_TERRAIN_URL,
  VACENTRAL_DARK_URL,
  VACENTRAL_LIGHT_URL,
} from "./basemaps.ts";
export type { FallbackRouteSummary } from "./fallback.ts";
export { supportsWebGL2 } from "./browser.ts";

// design.md D5's live-marker layer and D11's flat-route fallback — both plain helpers with no
// three.js in their own graph, same Tier 0 reasoning as the exports above.
// Planned-route waypoint dots/labels — two plain maplibre layers, no three.js, so Tier 0 safe.
export { createRouteWaypoints, LEG_COLORS } from "./route-waypoints.ts";
export type {
  RouteLegKind,
  RouteWaypoint,
  RouteWaypointLayer,
  RouteWaypointsOptions,
} from "./route-waypoints.ts";

export { createLiveFlightMarkers } from "./live-markers.ts";
export type {
  LiveFlightMarkers,
  LiveFlightMarkersOptions,
  LiveFlightPoint,
} from "./live-markers.ts";
export { resolvePlannedRoute } from "./planned-route.ts";

/** What both tiers' route layers can do — the surface `createRoute` promises regardless of tier. */
export type RouteLayer = {
  setData(points: RoutePoint[]): void;
  /**
   * Re-evaluate chunk LOD against the camera (design.md D14). Present only for Tier 1
   * (`RouteLine`, `route-line.ts`) — Tier 0 (`Tier0RouteLine`) has no chunking/LOD concept, so
   * this is `undefined` there. A caller wires this to `map.on('zoomend'|'moveend', ...)` and
   * calls it unconditionally via `layer.updateLod?.()`, matching both adapters (`imperative.ts`,
   * `adapters/vue/useRoute.ts`) — confirmed a real gap (codex review, verified): `updateLod`
   * existed on `RouteLine` since tasks.md 2.5 but this type never surfaced it, so no consumer
   * of `createRoute()`'s return value could reach it without an unsafe cast, and in practice
   * neither adapter ever called it — production never actually re-evaluated LOD after the first
   * paint.
   */
  updateLod?(): void;
  dispose(): void;
};

export type CreateRouteOptions = {
  defaultColor?: string;
  phaseColors?: Record<string, string>;
  lineWidthPx?: number;
  id?: string;
};

/**
 * Create a route layer at the given tier. Tier 1's module (`route-line.ts`,
 * and the 3D engine it pulls in) is fetched only when `tier === 1` — this
 * function is the package's one tiering boundary; do not add a second static
 * import of `route-line.ts` anywhere a Tier-0-only surface might reach. (Its
 * exact class names are deliberately not spelled out here in prose —
 * `tiering.build.test.ts` greps the built Tier 0 chunk for them, and a doc
 * comment mentioning them by name would bundle straight into that chunk and
 * trip the very check meant to catch a real regression.)
 *
 * Tier 0's own `createTier0RouteLine` is imported statically above, not
 * dynamically here — it is ALREADY re-exported eagerly (line 33), so a
 * `await import("./tier0-line.ts")` inside this `if` would just resolve to
 * the same already-bundled module rather than moving it into a separate
 * chunk (confirmed by a real build: rolldown's own
 * `INEFFECTIVE_DYNAMIC_IMPORT` warning, not a guess).
 */
export async function createRoute(
  map: MapLibreMapType,
  tier: 0 | 1,
  options: CreateRouteOptions = {},
): Promise<RouteLayer> {
  if (tier === 0) {
    return createTier0RouteLine(map, options);
  }
  const { createRouteLine } = await import("./route-line.ts");
  return createRouteLine(map, options);
}

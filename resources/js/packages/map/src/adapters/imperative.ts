import { createMap, type CreateMapOptions, whenLoaded } from "../base-map.ts";
import { createRoute } from "../index.ts";
import { createLiveFlightMarkers, type LiveFlightPoint } from "../live-markers.ts";
import { frameToRoute } from "../camera.ts";
import { resolvePlannedRoute } from "../planned-route.ts";
import type { PlannedFix, RoutePoint } from "../types.ts";

/**
 * The imperative adapter (design.md D1, tasks.md 6.1) — matches how admin
 * blades call maps today: `window.phpvms.map.render_*({ render_elem, ...})`,
 * lazy-loaded via `resources/js/apps/admin/app.js`'s existing pattern (see
 * that file's own `loadMaps`/`loadPhpvmsMap`). `render_route_map`'s single
 * options object with a `render_elem` string id is the shape kept here —
 * `el` accepts either an id string or an element directly, since some future
 * caller may already hold the element (e.g. a Livewire `wire:ignore` root).
 *
 * Both `renderPirepMap` (PIREP route) and `renderLiveMap` (live flights
 * overview) are Tier 1 and Tier 0 respectively, per design.md D13: the PIREP
 * route is drawn at altitude (`createRoute(map, 1, ...)`), the live map's
 * aircraft positions are an ordinary GeoJSON layer (`live-markers.ts`,
 * design.md D5) with no 3D engine — the tier is never inferred from anything
 * about the surface, the caller states it by calling the right function.
 */

function resolveElement(el: HTMLElement | string): HTMLElement | undefined {
  if (typeof el !== "string") return el;
  return document.getElementById(el) ?? undefined;
}

export type PirepMapOptions = Pick<CreateMapOptions, "config" | "theme" | "fallback"> & {
  /** `MapFlownData.points` — empty for a PIREP with no ACARS track. */
  flown: RoutePoint[];
  /** `MapPlannedRouteData.fixes`. */
  planned: PlannedFix[];
  /**
   * `MapPlannedRouteData.fallbackAltitudeFt` — the design.md D11 flat-route fallback for fixes
   * with no altitude of their own. ALREADY IN FEET, derived server-side from `altitude_msl`
   * telemetry; `resolvePlannedRoute` applies no FL→ft conversion. Passing a flight level here
   * (e.g. `MapPirepDetailData.cruiseLevel`) would render ~100x too low.
   */
  fallbackAltitudeFt: number | null;
  phaseColors?: Record<string, string>;
};

export type PirepMapHandle = { destroy(): void };

export async function renderPirepMap(
  el: HTMLElement | string,
  options: PirepMapOptions,
): Promise<PirepMapHandle | undefined> {
  const container = resolveElement(el);
  if (!container) return undefined;

  const result = await createMap(container, {
    config: options.config,
    theme: options.theme,
    fallback: options.fallback,
  });
  if (!result.ok) return undefined;
  const { map, destroy } = result;

  // `createRoute`/`createLiveFlightMarkers` self-register via `map.addLayer`/`addSource`
  // (route-line.ts, tier0-line.ts, live-markers.ts), which maplibre throws on ("Style is not
  // done loading") if called before the style has finished loading — `createMap` resolves as
  // soon as the `Map` is constructed, NOT once its style is ready, so this must be awaited
  // before creating any layer, not just before the first `setData`.
  await whenLoaded(map);

  const [flownRoute, plannedRoute] = await Promise.all([
    createRoute(map, 1, { phaseColors: options.phaseColors, id: "pirep-flown" }),
    createRoute(map, 1, { defaultColor: "#8B008B", id: "pirep-planned" }),
  ]);

  const plannedPoints = resolvePlannedRoute(options.planned, options.fallbackAltitudeFt);

  flownRoute.setData(options.flown);
  plannedRoute.setData(plannedPoints);

  // Framed exactly once, at setup — a PIREP's route never changes after this, so there is no
  // later `frameToRoute` call for `onUserTakesControl` (`camera.ts`) to need to guard against.
  frameToRoute(map, [...options.flown, ...plannedPoints]);

  // design.md D14: re-evaluate LOD as the camera moves. Without this, both routes stay at
  // whatever chunk selection `setData` picked at load time forever — confirmed a real gap
  // (codex review, verified): `updateLod` existed on `RouteLine` since tasks.md 2.5, but no
  // adapter ever called it, so production never actually merges/culls chunks after the first
  // paint. `updateLod` is a no-op for a Tier 0 layer (`Tier0RouteLine` has none), so this is
  // safe to wire unconditionally even though both routes here happen to be Tier 1.
  const updateLod = () => {
    flownRoute.updateLod?.();
    plannedRoute.updateLod?.();
  };
  map.on("zoomend", updateLod);
  map.on("moveend", updateLod);

  return {
    destroy() {
      map.off("zoomend", updateLod);
      map.off("moveend", updateLod);
      flownRoute.dispose();
      plannedRoute.dispose();
      destroy();
    },
  };
}

export type LiveMapOptions = Pick<CreateMapOptions, "config" | "theme" | "fallback"> & {
  flights: LiveFlightPoint[];
};

export type LiveMapHandle = {
  /** Wholesale replace the marker set — call this on every poll tick. */
  setFlights(flights: LiveFlightPoint[]): void;
  destroy(): void;
};

export async function renderLiveMap(
  el: HTMLElement | string,
  options: LiveMapOptions,
): Promise<LiveMapHandle | undefined> {
  const container = resolveElement(el);
  if (!container) return undefined;

  const result = await createMap(container, {
    config: options.config,
    theme: options.theme,
    fallback: options.fallback,
  });
  if (!result.ok) return undefined;
  const { map, destroy } = result;

  // See the matching comment in `renderPirepMap` — `createLiveFlightMarkers` self-registers via
  // `map.addSource`/`addLayer`, which needs the style loaded first.
  await whenLoaded(map);
  const markers = createLiveFlightMarkers(map, { id: "live-flights" });
  markers.setData(options.flights);

  return {
    setFlights: (flights) => markers.setData(flights),
    destroy() {
      markers.dispose();
      destroy();
    },
  };
}

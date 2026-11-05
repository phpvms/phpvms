import type { PlannedFix, RoutePoint } from "./types.ts";

/**
 * Resolve a planned route's fixes to at-altitude points (design.md D11).
 *
 * A fix with a null `altitudeFt` (an old archive, or a non-SimBrief PIREP —
 * `PirepArchiveService::buildNavlog()` only started retaining altitude going
 * forward, no backfill) falls back to the route's flat fallback altitude
 * rather than being dropped or drawn at 0. That fallback may itself be `null`
 * (a briefing, or a prefiled PIREP with no ACARS track yet) — a fix with no
 * altitude AND no fallback falls back to ground level (0), which is honest:
 * there is nothing left to reconstruct it from.
 *
 * `fallbackAltitudeFt` is `MapPlannedRouteData.fallbackAltitudeFt`, ALREADY IN
 * FEET — derived server-side in `MapController::plannedFallbackAltitudeFt()`
 * from real `altitude_msl` telemetry. Do NOT apply an FL→ft conversion here.
 * This parameter used to be `cruiseFlightLevel` (`$pirep->level`, a flight
 * level) and did multiply by 100; it was retargeted because `level` turned out
 * to hold feet in 32 of 33 real rows, so the multiply produced a 100x error on
 * the majority of the data. Passing a flight level here now would render ~100x
 * too low.
 */
const FEET_TO_METRES = 0.3048;

export function resolvePlannedRoute(
  fixes: PlannedFix[],
  fallbackAltitudeFt: number | null,
): RoutePoint[] {
  return fixes.map((fix) => ({
    lat: fix.lat,
    lon: fix.lon,
    altitude: (fix.altitudeFt ?? fallbackAltitudeFt ?? 0) * FEET_TO_METRES,
  }));
}

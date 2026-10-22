import type { LngLatBoundsLike, Map as MapLibreMapType } from "maplibre-gl";

/**
 * Camera framing (design.md map-rendering spec "Globe projection with
 * route-fitted camera", tasks.md 3.2): fit to the route's extent, pitched
 * rather than straight down, and release automatic framing the moment the
 * user takes control so a subsequent data refresh does not yank the camera
 * back mid-interaction.
 */

const DEFAULT_PITCH = 45;
const DEFAULT_PADDING_PX = 60;

export type FramePoint = { lat: number; lon: number };

/**
 * The west/east span for a set of longitudes, antimeridian-aware. Confirmed a real bug (codex
 * review, verified): a naive `min`/`max` over raw longitudes reads a 179.9°/-179.9° route as
 * spanning ~359.8° (from -179.9 to 179.9 the "long way", through 0°) instead of the actual
 * ~0.2° it covers — `frameToRoute` would zoom out to fit nearly the whole globe.
 *
 * Standard "largest empty arc" technique: sort the longitudes, find the biggest gap between
 * consecutive values on the CIRCLE (wrapping from the highest back to the lowest + 360°), and
 * the bounds span everything OUTSIDE that gap — i.e. from just after it to just before it. A
 * route that does not cross the antimeridian has its largest gap on the "far side" of the
 * globe from all its points, so this reduces to the ordinary min/max for that case.
 *
 * Confirmed against maplibre-gl's own source (`LngLatBounds.adjustAntiMeridian()`,
 * `maplibre-gl-dev.js`): `west > east` is the documented convention for a wrapping box —
 * `cameraForBounds()` (which `map.fitBounds()` calls internally) detects exactly that and adds
 * 360° to the east side before framing, so this function is free to return `west > east` as-is
 * rather than needing to pre-adjust it itself.
 */
function lonSpan(lons: number[]): { west: number; east: number } {
  const sorted = [...new Set(lons)].sort((a, b) => a - b);
  if (sorted.length === 1) return { west: sorted[0], east: sorted[0] };

  let largestGap = -Infinity;
  let gapStartIndex = 0;
  for (let i = 0; i < sorted.length; i++) {
    const next = i === sorted.length - 1 ? sorted[0] + 360 : sorted[i + 1];
    const gap = next - sorted[i];
    if (gap > largestGap) {
      largestGap = gap;
      gapStartIndex = i;
    }
  }
  return {
    west: sorted[(gapStartIndex + 1) % sorted.length],
    east: sorted[gapStartIndex],
  };
}

/** `[west, south, east, north]` enclosing every point. `undefined` for fewer than one point. */
export function boundsFor(points: FramePoint[]): LngLatBoundsLike | undefined {
  if (points.length === 0) return undefined;
  const { west, east } = lonSpan(points.map((p) => p.lon));
  let south = points[0].lat;
  let north = points[0].lat;
  for (const point of points) {
    south = Math.min(south, point.lat);
    north = Math.max(north, point.lat);
  }
  return [west, south, east, north];
}

export type FrameOptions = { pitch?: number; paddingPx?: number; durationMs?: number };

/** Fit the camera to the route's extent, pitched. A single point (or none) leaves the camera untouched. */
export function frameToRoute(
  map: MapLibreMapType,
  points: FramePoint[],
  options: FrameOptions = {},
): void {
  const bounds = boundsFor(points);
  if (!bounds) return;
  map.fitBounds(bounds, {
    pitch: options.pitch ?? DEFAULT_PITCH,
    padding: options.paddingPx ?? DEFAULT_PADDING_PX,
    duration: options.durationMs ?? 0,
  });
}

/**
 * Wire the map so any USER-driven camera movement (drag, scroll, pinch —
 * anything carrying a real DOM `originalEvent`) calls `onUserInteracted`
 * exactly once, and never fires for a programmatic move (`fitBounds`,
 * `flyTo`, `easeTo`, `jumpTo` all fire `movestart` with no `originalEvent`).
 * Returns a cleanup function; callers stop listening once framing has been
 * released, since there is nothing further for this to do.
 */
export function onUserTakesControl(map: MapLibreMapType, onUserInteracted: () => void): () => void {
  const handler = (e: { originalEvent?: unknown }) => {
    if (e.originalEvent) {
      onUserInteracted();
      map.off("movestart", handler);
    }
  };
  map.on("movestart", handler);
  return () => map.off("movestart", handler);
}

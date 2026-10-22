import type { FeatureCollection, LineString } from "geojson";
import type { GeoJSONSource, Map as MapLibreMapType } from "maplibre-gl";
import type { RoutePoint } from "./types.ts";

/**
 * Tier 0 (design.md D13): an ordinary maplibre `line` layer, imported by NO
 * three.js — small surfaces (dashboard `RouteWidget`, `FlightDetailPanel`,
 * briefing overview) pay nothing for the 3D engine `route-line.ts` needs.
 * Ground-projected only; altitude is geometrically invisible at these sizes
 * anyway (cruise is ~0.17% of Earth's radius, per D13), so there is nothing
 * lost by not drawing it here.
 *
 * maplibre's `line-color` is a per-FEATURE style property, not per-vertex —
 * `LineGeometry.setColors()`'s per-vertex blend (`route-line.ts`) has no
 * Tier-0 equivalent. Phase colouring here is one LineString feature per
 * contiguous run of the same phase (design.md D3's documented fallback for
 * exactly this situation: "phase colouring falls back... to one feature per
 * run of constant PirepPhase"), styled with a `match` expression on `phase`.
 */

export type PhaseColorMap = Record<string, string>;

export type Tier0RouteLineOptions = {
  defaultColor?: string;
  phaseColors?: PhaseColorMap;
  lineWidthPx?: number;
  /** MapLibre source/layer id — must be unique per instance on one map (same reason as `route-line.ts`'s `id`). */
  id?: string;
};

export type Tier0RouteLine = {
  /** Wholesale rebuild (design.md D4/D7 — same `setData` semantics as Tier 1's `route-line.ts`). */
  setData(points: RoutePoint[]): void;
  dispose(): void;
};

const DEFAULT_COLOR = "#e879f9";
const DEFAULT_WIDTH_PX = 3;
let anonymousTier0Count = 0;

export type PhaseRun<T extends { phase?: string }> = { phase: string | undefined; points: T[] };

/**
 * Split a point list into runs of consecutive points sharing the same
 * `phase` — consecutive runs share their joining point, matching
 * `chunking.ts`'s `chunkLine` convention, so adjacent LineString features
 * meet without a gap.
 */
export function groupByPhaseRuns<T extends { phase?: string }>(points: T[]): PhaseRun<T>[] {
  if (points.length < 2) return [];

  const runs: PhaseRun<T>[] = [];
  let current: T[] = [points[0]];
  let currentPhase = points[0].phase;

  for (const point of points.slice(1)) {
    if (point.phase !== currentPhase) {
      current.push(point); // shared joining point, so the runs meet without a gap
      runs.push({ phase: currentPhase, points: current });
      current = [point];
      currentPhase = point.phase;
    } else {
      current.push(point);
    }
  }
  if (current.length > 1) runs.push({ phase: currentPhase, points: current });
  return runs;
}

function toFeatureCollection(
  points: RoutePoint[],
): FeatureCollection<LineString, { phase: string | null }> {
  return {
    type: "FeatureCollection",
    features: groupByPhaseRuns(points).map((run) => ({
      type: "Feature",
      properties: { phase: run.phase ?? null },
      geometry: { type: "LineString", coordinates: run.points.map((p) => [p.lon, p.lat]) },
    })),
  };
}

/** A maplibre `match` expression: known phases get their configured colour, everything else `defaultColor`. */
function lineColorExpression(phaseColors: PhaseColorMap, defaultColor: string): unknown[] {
  const entries = Object.entries(phaseColors);
  if (entries.length === 0) return ["literal", defaultColor];
  return [
    "match",
    ["get", "phase"],
    ...entries.flatMap(([phase, color]) => [phase, color]),
    defaultColor,
  ];
}

export function createTier0RouteLine(
  map: MapLibreMapType,
  options: Tier0RouteLineOptions = {},
): Tier0RouteLine {
  const id = options.id ?? `tier0-route-line-${anonymousTier0Count++}`;
  const sourceId = `${id}-source`;
  const defaultColor = options.defaultColor ?? DEFAULT_COLOR;
  const phaseColors = options.phaseColors ?? {};

  map.addSource(sourceId, { type: "geojson", data: { type: "FeatureCollection", features: [] } });
  map.addLayer({
    id,
    type: "line",
    source: sourceId,
    layout: { "line-join": "round", "line-cap": "round" },
    paint: {
      "line-width": options.lineWidthPx ?? DEFAULT_WIDTH_PX,
      // `as never`: maplibre's expression type is a large recursive union that a plain array
      // literal never satisfies structurally, even though it is the documented runtime form.
      "line-color": lineColorExpression(phaseColors, defaultColor) as never,
    },
  });

  return {
    setData(points: RoutePoint[]) {
      const source = map.getSource(sourceId) as GeoJSONSource;
      source.setData(toFeatureCollection(points));
    },
    dispose() {
      if (map.getLayer(id)) map.removeLayer(id);
      if (map.getSource(sourceId)) map.removeSource(sourceId);
    },
  };
}

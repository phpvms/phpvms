import type { FeatureCollection, Point } from "geojson";
import type { GeoJSONSource, Map as MapLibreMapType } from "maplibre-gl";

/**
 * Planned-route waypoint dots and ident labels — Tier 0 (design.md D13): two
 * ordinary maplibre layers, no three.js in this module's graph.
 *
 * Ported from acars's FLAT renderer (`scene-surface.ts`), not its at-altitude
 * one. acars draws its briefing route through a three.js tube with troika text
 * because that surface sets `style.altitude = true`; phpVMS's briefing map is a
 * ground-projected Tier 0 line, so the flat circle/symbol layer values are the
 * matching port. Colours are acars's own leg palette (`briefing-route.ts`).
 *
 * LABELS DEPEND ON THE BASEMAP HAVING GLYPHS. A maplibre `symbol` layer with a
 * `text-field` renders nothing — silently, with no error — when the active
 * style has no `glyphs` URL. The vacentral styles carry one (font stack
 * `ManropeRegular-16`, which is also what acars uses); the bundled ESRI style
 * and the offline `blankStyle` fallback deliberately do not (`basemaps.ts`
 * documents exactly this). So the label layer is added only when the style
 * actually has glyphs, and the dots — which need none — are always drawn.
 */

/** acars `briefing-route.ts` LEG_COLORS. */
export const LEG_COLORS = {
  sid: "#34d399",
  enroute: "#e879f9",
  star: "#fbbf24",
} as const;

export type RouteLegKind = keyof typeof LEG_COLORS;

/** acars `scene-surface.ts` uses this as the casing/halo colour throughout. */
const CASING_COLOR = "#0b1420";
const LABEL_COLOR = "#f4f4f5";
const DEFAULT_POINT_RADIUS = 4;

export type RouteWaypoint = {
  lat: number;
  lon: number;
  ident: string | null;
  kind?: RouteLegKind;
};

export type RouteWaypointsOptions = {
  id?: string;
  pointRadius?: number;
  /**
   * Font stack for the ident labels. Must exist in the active style's glyph
   * endpoint; `ManropeRegular-16` is what the vacentral styles publish.
   */
  labelFont?: string[];
};

export type RouteWaypointLayer = {
  setData(waypoints: RouteWaypoint[]): void;
  dispose(): void;
};

let anonymousWaypointCount = 0;

function toFeatureCollection(
  waypoints: RouteWaypoint[],
): FeatureCollection<Point, { ident: string; kind: string }> {
  return {
    type: "FeatureCollection",
    features: waypoints.map((waypoint) => ({
      type: "Feature",
      properties: {
        ident: waypoint.ident ?? "",
        kind: waypoint.kind ?? "enroute",
      },
      geometry: { type: "Point", coordinates: [waypoint.lon, waypoint.lat] },
    })),
  };
}

/** A maplibre `match` on the leg kind, mirroring acars's `colorFor`. */
function legColorExpression(): unknown[] {
  return [
    "match",
    ["get", "kind"],
    ...Object.entries(LEG_COLORS).flatMap(([kind, color]) => [kind, color]),
    LEG_COLORS.enroute,
  ];
}

/** Whether the active style can resolve glyphs — see this module's header. */
function styleHasGlyphs(map: MapLibreMapType): boolean {
  try {
    return typeof map.getStyle().glyphs === "string";
  } catch {
    // getStyle() throws if the style is not loaded yet; treat as "no labels".
    return false;
  }
}

export function createRouteWaypoints(
  map: MapLibreMapType,
  options: RouteWaypointsOptions = {},
): RouteWaypointLayer {
  const id = options.id ?? `route-waypoints-${anonymousWaypointCount++}`;
  const sourceId = `${id}-source`;
  const dotLayerId = `${id}-dots`;
  const labelLayerId = `${id}-labels`;
  const radius = options.pointRadius ?? DEFAULT_POINT_RADIUS;

  map.addSource(sourceId, {
    type: "geojson",
    data: { type: "FeatureCollection", features: [] },
  });

  map.addLayer({
    id: dotLayerId,
    type: "circle",
    source: sourceId,
    paint: {
      "circle-radius": radius,
      "circle-color": legColorExpression() as never,
      "circle-stroke-width": 2,
      "circle-stroke-color": CASING_COLOR,
    },
  });

  const labelled = styleHasGlyphs(map);
  if (labelled) {
    map.addLayer({
      id: labelLayerId,
      type: "symbol",
      source: sourceId,
      layout: {
        "text-field": ["get", "ident"] as never,
        "text-font": options.labelFont ?? ["ManropeRegular-16"],
        "text-size": 18,
        "text-letter-spacing": 0.06,
        "text-variable-anchor": ["left", "right", "top", "bottom"],
        "text-radial-offset": 1.2,
        "text-padding": 4,
        // acars declutters rather than overlapping; dropped labels are expected.
        "text-allow-overlap": false,
      },
      paint: {
        "text-color": LABEL_COLOR,
        "text-halo-color": CASING_COLOR,
        "text-halo-width": 1.8,
        "text-halo-blur": 0.4,
      },
    });
  }

  return {
    setData(waypoints: RouteWaypoint[]): void {
      const source = map.getSource(sourceId) as GeoJSONSource | undefined;
      // The source is gone if the style was swapped underneath us (theme change).
      source?.setData(toFeatureCollection(waypoints));
    },
    dispose(): void {
      // `Map#remove()` does `delete this.style`, and `getLayer`/`getSource` both read through
      // it, so disposing AFTER the map was destroyed throws "can't access property getLayer,
      // this.style is undefined". That ordering is the normal case, not an edge one: a Vue host
      // registers `useMap`'s unmount hook before this layer's, so the map is already gone by the
      // time this runs. Seen in a real browser console on the briefing page. Nothing is leaked
      // by returning early — `remove()` already took the style, its layers and its sources.
      try {
        if (labelled && map.getLayer(labelLayerId)) map.removeLayer(labelLayerId);
        if (map.getLayer(dotLayerId)) map.removeLayer(dotLayerId);
        if (map.getSource(sourceId)) map.removeSource(sourceId);
      } catch {
        // already torn down
      }
    },
  };
}

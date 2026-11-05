import type { FeatureCollection, Point } from "geojson";
import type { GeoJSONSource, Map as MapLibreMapType } from "maplibre-gl";

/**
 * Live aircraft positions as a maplibre `symbol`/`circle` layer over a
 * GeoJSON source (design.md D5) — NOT the Tier 1 anchored-chunk substrate.
 * At a globe framing cruise altitude is geometrically invisible (~0.17% of
 * Earth's radius), so a live-map marker needs no altitude placement, and a
 * VA with hundreds of live flights needs this to stay cheap: one source, one
 * layer, wholesale-replaced on every poll — never diffed per marker.
 *
 * A `circle` paint layer, not an icon `symbol` layer: no aircraft icon asset
 * is bundled with this package, so heading-based icon rotation
 * (`MapPositionData.heading`) is not implemented here. Flagged as an open
 * item, same spirit as `LabelFont.url` — whoever owns the icon asset can
 * layer that in without changing this module's data contract.
 */

export type LiveFlightPoint = { pirepId: string; lat: number; lon: number };

export type LiveFlightMarkersOptions = {
  color?: string;
  radiusPx?: number;
  /** MapLibre source/layer id — unique per instance on one map, same reason as `route-line.ts`'s `id`. */
  id?: string;
};

export type LiveFlightMarkers = {
  setData(flights: LiveFlightPoint[]): void;
  dispose(): void;
};

const DEFAULT_COLOR = "#22c55e";
const DEFAULT_RADIUS_PX = 5;
let anonymousCount = 0;

function toFeatureCollection(
  flights: LiveFlightPoint[],
): FeatureCollection<Point, { pirepId: string }> {
  return {
    type: "FeatureCollection",
    features: flights.map((f) => ({
      type: "Feature",
      properties: { pirepId: f.pirepId },
      geometry: { type: "Point", coordinates: [f.lon, f.lat] },
    })),
  };
}

export function createLiveFlightMarkers(
  map: MapLibreMapType,
  options: LiveFlightMarkersOptions = {},
): LiveFlightMarkers {
  const id = options.id ?? `live-flight-markers-${anonymousCount++}`;
  const sourceId = `${id}-source`;

  map.addSource(sourceId, { type: "geojson", data: { type: "FeatureCollection", features: [] } });
  map.addLayer({
    id,
    type: "circle",
    source: sourceId,
    paint: {
      "circle-radius": options.radiusPx ?? DEFAULT_RADIUS_PX,
      "circle-color": options.color ?? DEFAULT_COLOR,
      "circle-stroke-width": 1,
      "circle-stroke-color": "#0b1420",
    },
  });

  return {
    setData(flights: LiveFlightPoint[]) {
      (map.getSource(sourceId) as GeoJSONSource).setData(toFeatureCollection(flights));
    },
    dispose() {
      if (map.getLayer(id)) map.removeLayer(id);
      if (map.getSource(sourceId)) map.removeSource(sourceId);
    },
  };
}

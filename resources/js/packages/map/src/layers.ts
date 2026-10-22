import type {
  Map as MapLibreMapType,
  RasterSourceSpecification,
  VectorSourceSpecification,
} from "maplibre-gl";
import type { MapLayerConfig } from "./types.ts";

/**
 * Apply configured overlay layers (design.md D9, tasks.md 3.5) — a
 * `map_layers` row per layer, `raster` or `vector`, in the order
 * `MapConfigService::resolve()` already sorted and filtered to enabled ones
 * (the generated `MapLayerData` carries neither an `order` nor an `enabled`
 * field, so both are server-side decisions this module trusts rather than
 * re-implements).
 */

const LAYER_ID_PREFIX = "phpvms-overlay-";

export function layerSourceId(layer: MapLayerConfig): string {
  return `${LAYER_ID_PREFIX}${layer.id}-source`;
}

export function layerId(layer: MapLayerConfig): string {
  return `${LAYER_ID_PREFIX}${layer.id}`;
}

/**
 * `urlTemplate` with `{apiKey}` substituted, matching the convention already
 * seeded for OpenAIP (`.../{z}/{x}/{y}.png?apiKey={apiKey}`,
 * `database/migrations_data/2026_08_31_000000_seed_map_layers.php`).
 * `{z}`/`{x}`/`{y}`/`{bbox-epsg-3857}` are maplibre's own raster-source
 * placeholders (confirmed present in maplibre-gl's own bundle — METAR's
 * seeded WMS row uses the latter) and are left untouched; this only ever
 * touches the one placeholder that is THIS package's job to fill in.
 */
export function resolveTileUrl(layer: MapLayerConfig): string {
  if (!layer.apiKey) return layer.urlTemplate;
  return layer.urlTemplate.replaceAll("{apiKey}", layer.apiKey);
}

/**
 * `vector` layers have no rendering hints in `MapLayerData` beyond the URL
 * (no `source-layer`, no paint style) — no seeded row uses `type: vector`
 * yet to prove a real shape out of. This renders a plain themeless line as a
 * placeholder rather than silently doing nothing; flagging it as an open
 * question for whoever seeds the first real vector overlay, same spirit as
 * `LabelFont.url` staying undecided rather than guessed.
 */
const VECTOR_PLACEHOLDER_SOURCE_LAYER = "default";

export function applyLayers(map: MapLibreMapType, layers: MapLayerConfig[]): void {
  for (const layer of layers) {
    const sourceId = layerSourceId(layer);
    const id = layerId(layer);
    if (map.getLayer(id)) continue; // already applied — setData-style idempotency, no duplicate add

    if (layer.type === "vector") {
      const source: VectorSourceSpecification = {
        type: "vector",
        tiles: [resolveTileUrl(layer)],
        attribution: layer.attribution ?? undefined,
        minzoom: layer.minZoom,
        maxzoom: layer.maxZoom,
      };
      map.addSource(sourceId, source);
      map.addLayer({
        id,
        type: "line",
        source: sourceId,
        "source-layer": VECTOR_PLACEHOLDER_SOURCE_LAYER,
        minzoom: layer.minZoom,
        maxzoom: layer.maxZoom,
        paint: { "line-opacity": layer.opacity },
      });
      continue;
    }

    const source: RasterSourceSpecification = {
      type: "raster",
      tiles: [resolveTileUrl(layer)],
      tileSize: 256,
      attribution: layer.attribution ?? undefined,
      minzoom: layer.minZoom,
      maxzoom: layer.maxZoom,
    };
    map.addSource(sourceId, source);
    map.addLayer({
      id,
      type: "raster",
      source: sourceId,
      minzoom: layer.minZoom,
      maxzoom: layer.maxZoom,
      paint: { "raster-opacity": layer.opacity },
    });
  }
}

export function removeLayers(map: MapLibreMapType, layers: MapLayerConfig[]): void {
  for (const layer of layers) {
    const id = layerId(layer);
    const sourceId = layerSourceId(layer);
    if (map.getLayer(id)) map.removeLayer(id);
    if (map.getSource(sourceId)) map.removeSource(sourceId);
  }
}

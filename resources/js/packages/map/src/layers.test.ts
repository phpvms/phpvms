import { describe, expect, it, vi } from "vitest";
import { applyLayers, layerId, layerSourceId, removeLayers, resolveTileUrl } from "./layers.ts";
import type { MapLayerConfig } from "./types.ts";

function fakeLayer(overrides: Partial<MapLayerConfig> = {}): MapLayerConfig {
  return {
    id: 1,
    name: "Test layer",
    type: "raster",
    urlTemplate: "https://tiles.example.com/{z}/{x}/{y}.png",
    attribution: null,
    minZoom: 0,
    maxZoom: 19,
    opacity: 1,
    apiKey: null,
    surfaces: null,
    ...overrides,
  };
}

function fakeMap() {
  const addedSources: string[] = [];
  const addedLayers: string[] = [];
  const removedLayers: string[] = [];
  const removedSources: string[] = [];
  return {
    addedSources,
    addedLayers,
    removedLayers,
    removedSources,
    addSource: vi.fn((id: string) => addedSources.push(id)),
    addLayer: vi.fn(
      (spec: {
        id: string;
        type: string;
        minzoom?: number;
        maxzoom?: number;
        paint?: Record<string, unknown>;
      }) => addedLayers.push(spec.id),
    ),
    getLayer: vi.fn((id: string) => (addedLayers.includes(id) ? {} : undefined)),
    getSource: vi.fn((id: string) => (addedSources.includes(id) ? {} : undefined)),
    removeLayer: vi.fn((id: string) => removedLayers.push(id)),
    removeSource: vi.fn((id: string) => removedSources.push(id)),
  };
}

describe("resolveTileUrl", () => {
  it("leaves the URL unchanged when there is no api key", () => {
    const layer = fakeLayer({
      urlTemplate: "https://tiles.example.com/{z}/{x}/{y}.png?apiKey={apiKey}",
    });
    expect(resolveTileUrl(layer)).toBe("https://tiles.example.com/{z}/{x}/{y}.png?apiKey={apiKey}");
  });

  it("substitutes {apiKey}, matching the seeded OpenAIP url_template convention", () => {
    const layer = fakeLayer({
      urlTemplate: "https://api.tiles.openaip.net/api/data/openaip/{z}/{x}/{y}.png?apiKey={apiKey}",
      apiKey: "secret-123",
    });
    expect(resolveTileUrl(layer)).toBe(
      "https://api.tiles.openaip.net/api/data/openaip/{z}/{x}/{y}.png?apiKey=secret-123",
    );
  });

  it("leaves maplibre's own placeholders ({z}/{x}/{y}/{bbox-epsg-3857}) untouched", () => {
    const layer = fakeLayer({
      urlTemplate:
        "https://wms.example.com/?bbox={bbox-epsg-3857}&z={z}&x={x}&y={y}&apiKey={apiKey}",
      apiKey: "k",
    });
    expect(resolveTileUrl(layer)).toBe(
      "https://wms.example.com/?bbox={bbox-epsg-3857}&z={z}&x={x}&y={y}&apiKey=k",
    );
  });
});

describe("applyLayers", () => {
  it("applies layers in the given order, honouring zoom range and opacity", () => {
    const map = fakeMap();
    const layers = [
      fakeLayer({ id: 1, minZoom: 4, maxZoom: 14, opacity: 0.5 }),
      fakeLayer({ id: 2, type: "vector" }),
    ];
    applyLayers(map as never, layers);

    expect(map.addedLayers).toEqual([layerId(layers[0]), layerId(layers[1])]);
    const rasterCall = map.addLayer.mock.calls[0][0];
    expect(rasterCall.minzoom).toBe(4);
    expect(rasterCall.maxzoom).toBe(14);
    expect(rasterCall.paint?.["raster-opacity"]).toBe(0.5);

    const vectorCall = map.addLayer.mock.calls[1][0];
    expect(vectorCall.type).toBe("line");
  });

  it("is idempotent — applying the same layer twice does not add it twice", () => {
    const map = fakeMap();
    const layer = fakeLayer();
    applyLayers(map as never, [layer]);
    applyLayers(map as never, [layer]);
    expect(map.addLayer).toHaveBeenCalledTimes(1);
    expect(map.addSource).toHaveBeenCalledTimes(1);
  });
});

describe("removeLayers", () => {
  it("removes layer and source only when both exist", () => {
    const map = fakeMap();
    const layer = fakeLayer();
    applyLayers(map as never, [layer]);
    removeLayers(map as never, [layer]);
    expect(map.removedLayers).toEqual([layerId(layer)]);
    expect(map.removedSources).toEqual([layerSourceId(layer)]);
  });

  it("does nothing for a layer that was never applied", () => {
    const map = fakeMap();
    removeLayers(map as never, [fakeLayer()]);
    expect(map.removeLayer).not.toHaveBeenCalled();
    expect(map.removeSource).not.toHaveBeenCalled();
  });
});

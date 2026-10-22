import { describe, expect, it, vi } from "vitest";
import { createLiveFlightMarkers } from "./live-markers.ts";

function fakeMap() {
  let sourceData: unknown;
  return {
    addSource: vi.fn(),
    addLayer: vi.fn(),
    getSource: vi.fn(() => ({ setData: vi.fn((d: unknown) => (sourceData = d)) })),
    getLayer: vi.fn(() => ({})),
    removeLayer: vi.fn(),
    removeSource: vi.fn(),
    get lastSourceData() {
      return sourceData;
    },
  };
}

describe("createLiveFlightMarkers", () => {
  it("adds one source and one circle layer, no icon dependency", () => {
    const map = fakeMap();
    createLiveFlightMarkers(map as never);
    expect(map.addSource).toHaveBeenCalledTimes(1);
    expect(map.addLayer).toHaveBeenCalledTimes(1);
    expect(map.addLayer.mock.calls[0][0].type).toBe("circle");
  });

  it("setData replaces the source wholesale with a Point FeatureCollection", () => {
    const map = fakeMap();
    const markers = createLiveFlightMarkers(map as never);
    markers.setData([
      { pirepId: "a", lat: 1, lon: 2 },
      { pirepId: "b", lat: 3, lon: 4 },
    ]);
    const data = map.lastSourceData as {
      features: { properties: { pirepId: string }; geometry: { coordinates: number[] } }[];
    };
    expect(data.features).toHaveLength(2);
    expect(data.features[0].properties.pirepId).toBe("a");
    expect(data.features[0].geometry.coordinates).toEqual([2, 1]); // [lon, lat]
  });

  it("dispose removes the layer and source when present", () => {
    const map = fakeMap();
    const markers = createLiveFlightMarkers(map as never);
    markers.dispose();
    expect(map.removeLayer).toHaveBeenCalledTimes(1);
    expect(map.removeSource).toHaveBeenCalledTimes(1);
  });
});

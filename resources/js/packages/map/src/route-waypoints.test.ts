import { describe, expect, it, vi } from "vitest";
import { createRouteWaypoints, LEG_COLORS } from "./route-waypoints.ts";

// `null`, not `undefined`: passing `undefined` for a defaulted parameter re-applies
// the default, which silently handed the "no glyphs" cases a glyphs URL.
function fakeMap(
  glyphs: string | null = "https://tiles.example.com/fonts/{fontstack}/{range}.pbf",
) {
  let sourceData: unknown;
  return {
    addSource: vi.fn(),
    addLayer: vi.fn(),
    getStyle: vi.fn(() => ({ glyphs: glyphs ?? undefined })),
    getSource: vi.fn(() => ({ setData: vi.fn((d: unknown) => (sourceData = d)) })),
    getLayer: vi.fn(() => ({})),
    removeLayer: vi.fn(),
    removeSource: vi.fn(),
    get lastSourceData() {
      return sourceData;
    },
  };
}

describe("createRouteWaypoints", () => {
  it("draws a dot layer and, on a style with glyphs, a label layer", () => {
    const map = fakeMap();
    createRouteWaypoints(map as never);

    const types = map.addLayer.mock.calls.map((call) => call[0].type);
    expect(types).toEqual(["circle", "symbol"]);
  });

  it("omits the label layer when the style has no glyphs, keeping the dots", () => {
    // A symbol layer with a text-field renders NOTHING and reports no error on a
    // style without a glyphs URL (the bundled ESRI style and the offline
    // fallback are both like this), so the labels must not be added at all.
    const map = fakeMap(null);
    createRouteWaypoints(map as never);

    const types = map.addLayer.mock.calls.map((call) => call[0].type);
    expect(types).toEqual(["circle"]);
  });

  it("survives a style that is not loaded enough to report its glyphs", () => {
    const map = fakeMap();
    map.getStyle = vi.fn(() => {
      throw new Error("Style is not done loading");
    });

    expect(() => createRouteWaypoints(map as never)).not.toThrow();
    expect(map.addLayer.mock.calls.map((call) => call[0].type)).toEqual(["circle"]);
  });

  it("setData writes a Point FeatureCollection carrying ident and leg kind", () => {
    const map = fakeMap();
    const layer = createRouteWaypoints(map as never);
    layer.setData([
      { lat: 1, lon: 2, ident: "BOMUP", kind: "sid" },
      { lat: 3, lon: 4, ident: null },
    ]);

    const data = map.lastSourceData as {
      features: {
        properties: { ident: string; kind: string };
        geometry: { coordinates: number[] };
      }[];
    };
    expect(data.features).toHaveLength(2);
    expect(data.features[0].properties).toEqual({ ident: "BOMUP", kind: "sid" });
    expect(data.features[0].geometry.coordinates).toEqual([2, 1]); // [lon, lat]
    // An unnamed fix still gets a dot; it just has no label text.
    expect(data.features[1].properties).toEqual({ ident: "", kind: "enroute" });
  });

  it("colours dots by leg kind with acars's palette", () => {
    const map = fakeMap();
    createRouteWaypoints(map as never);

    const circle = map.addLayer.mock.calls[0][0];
    const expression = circle.paint["circle-color"] as unknown[];
    expect(expression[0]).toBe("match");
    expect(expression).toContain(LEG_COLORS.sid);
    expect(expression).toContain(LEG_COLORS.star);
    // Trailing fallback for an unknown kind.
    expect(expression.at(-1)).toBe(LEG_COLORS.enroute);
  });

  it("dispose removes both layers and the source", () => {
    const map = fakeMap();
    const layer = createRouteWaypoints(map as never);
    layer.dispose();

    expect(map.removeLayer).toHaveBeenCalledTimes(2);
    expect(map.removeSource).toHaveBeenCalledTimes(1);
  });

  it("dispose survives a map that was already destroyed", () => {
    // The Vue host registers `useMap`'s unmount hook BEFORE the layer's, so on teardown the map
    // is removed first and `map.style` is gone. `getLayer` reads through it and throws — seen in
    // a real browser console on the briefing page as an unhandled error in an unmounted hook.
    const map = fakeMap();
    const layer = createRouteWaypoints(map as never);

    map.getLayer = vi.fn(() => {
      throw new TypeError('can\'t access property "getLayer", this.style is undefined');
    });

    expect(() => layer.dispose()).not.toThrow();
  });

  it("dispose does not try to remove a label layer that was never added", () => {
    const map = fakeMap(null);
    const layer = createRouteWaypoints(map as never);
    layer.dispose();

    expect(map.removeLayer).toHaveBeenCalledTimes(1);
  });
});

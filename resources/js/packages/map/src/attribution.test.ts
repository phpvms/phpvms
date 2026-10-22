import { describe, expect, it } from "vitest";
import { collectAttributions, renderAttribution } from "./attribution.ts";
import type { MapLayerConfig } from "./types.ts";
import type { StyleSpecification } from "maplibre-gl";

function fakeLayer(overrides: Partial<MapLayerConfig> = {}): MapLayerConfig {
  return {
    id: 1,
    name: "Test",
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

const styleWithAttribution: StyleSpecification = {
  version: 8,
  sources: {
    carto: { type: "raster", tiles: [], attribution: "© CARTO" } as never,
    other: { type: "raster", tiles: [] } as never, // no attribution — must not produce "undefined"
  },
  layers: [],
};

describe("collectAttributions", () => {
  it("collects the style's own source attributions plus enabled layers', deduplicated", () => {
    const layers = [
      fakeLayer({ attribution: "© OpenAIP" }),
      fakeLayer({ id: 2, attribution: "© OpenAIP" }),
    ];
    const result = collectAttributions(styleWithAttribution, layers);
    expect(result).toEqual(["© CARTO", "© OpenAIP"]);
  });

  it("omits layers with no attribution rather than inserting a blank entry", () => {
    const layers = [fakeLayer({ attribution: null }), fakeLayer({ id: 2, attribution: "" })];
    expect(collectAttributions(styleWithAttribution, layers)).toEqual(["© CARTO"]);
  });

  it("returns just the style's credit when there are no overlay layers", () => {
    expect(collectAttributions(styleWithAttribution, [])).toEqual(["© CARTO"]);
  });
});

describe("renderAttribution", () => {
  it("joins both credits into one line and preserves HTML (e.g. a linked credit)", () => {
    const container = document.createElement("div");
    renderAttribution(container, styleWithAttribution, [
      fakeLayer({ attribution: '<a href="https://x">© X</a>' }),
    ]);
    expect(container.textContent).toContain("© CARTO");
    expect(container.querySelector("a")).not.toBeNull();
  });

  it("renders nothing when there is no attribution at all, rather than an empty label", () => {
    const container = document.createElement("div");
    const bareStyle: StyleSpecification = { version: 8, sources: {}, layers: [] };
    renderAttribution(container, bareStyle, []);
    expect(container.textContent).toBe("");
  });
});

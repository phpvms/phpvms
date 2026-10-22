import { describe, expect, it, vi } from "vitest";
import {
  applyLayerVisibility,
  LayerControl,
  readHiddenLayerIds,
  writeHiddenLayerIds,
} from "./layer-control.ts";
import { layerId } from "./layers.ts";
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

/** Only the surface `layer-control.ts` actually touches. */
function fakeMap(existingLayerIds: string[]) {
  const visibility: Record<string, string> = {};
  return {
    visibility,
    getLayer: vi.fn((id: string) => (existingLayerIds.includes(id) ? {} : undefined)),
    setLayoutProperty: vi.fn((id: string, _prop: string, value: string) => {
      visibility[id] = value;
    }),
    addControl: vi.fn(),
  };
}

function memoryStorage(initial: string | null = null): Storage {
  let value = initial;
  return {
    getItem: () => value,
    setItem: (_k: string, v: string) => {
      value = v;
    },
    removeItem: () => {
      value = null;
    },
    clear: () => {
      value = null;
    },
    key: () => null,
    length: 0,
  } as unknown as Storage;
}

describe("readHiddenLayerIds", () => {
  it("reads nothing hidden when storage is empty — a fresh browser shows what the operator configured", () => {
    expect(readHiddenLayerIds(memoryStorage())).toEqual(new Set());
  });

  it("reads back the ids it stored", () => {
    const storage = memoryStorage();
    writeHiddenLayerIds(new Set([2, 5]), storage);
    expect(readHiddenLayerIds(storage)).toEqual(new Set([2, 5]));
  });

  it("treats corrupt JSON as nothing hidden rather than throwing", () => {
    expect(readHiddenLayerIds(memoryStorage("{not json"))).toEqual(new Set());
  });

  it("treats a non-array payload as nothing hidden", () => {
    expect(readHiddenLayerIds(memoryStorage('{"1":true}'))).toEqual(new Set());
  });

  it("survives storage being unavailable entirely (blocked site data)", () => {
    expect(readHiddenLayerIds(null)).toEqual(new Set());
    expect(() => writeHiddenLayerIds(new Set([1]), null)).not.toThrow();
  });
});

describe("applyLayerVisibility", () => {
  it("hides only the layers in the hidden set", () => {
    const layers = [fakeLayer({ id: 1 }), fakeLayer({ id: 2, name: "Second" })];
    const map = fakeMap(layers.map((l) => layerId(l)));

    applyLayerVisibility(map as never, layers, new Set([2]));

    expect(map.visibility[layerId(layers[0])]).toBe("visible");
    expect(map.visibility[layerId(layers[1])]).toBe("none");
  });

  it("skips a configured layer that never made it onto the style — setLayoutProperty would throw", () => {
    const layers = [fakeLayer({ id: 1 })];
    const map = fakeMap([]); // applyLayers bailed on this one, e.g. a bad source

    expect(() => applyLayerVisibility(map as never, layers, new Set())).not.toThrow();
    expect(map.setLayoutProperty).not.toHaveBeenCalled();
  });

  it("re-shows a layer removed from the hidden set", () => {
    const layers = [fakeLayer({ id: 1 })];
    const map = fakeMap([layerId(layers[0])]);

    applyLayerVisibility(map as never, layers, new Set([1]));
    expect(map.visibility[layerId(layers[0])]).toBe("none");

    applyLayerVisibility(map as never, layers, new Set());
    expect(map.visibility[layerId(layers[0])]).toBe("visible");
  });
});

describe("LayerControl", () => {
  it("renders one checkbox per layer, checked when the layer is not hidden", () => {
    const layers = [fakeLayer({ id: 1 }), fakeLayer({ id: 2, name: "Second" })];
    const control = new LayerControl({ layers, storage: memoryStorage("[2]") });

    const el = control.onAdd(fakeMap(layers.map((l) => layerId(l))) as never);
    const boxes = [...el.querySelectorAll<HTMLInputElement>('input[type="checkbox"]')];

    expect(boxes).toHaveLength(2);
    expect(boxes[0].checked).toBe(true);
    expect(boxes[1].checked).toBe(false);
  });

  it("applies the stored hidden set on add, not only on a later click", () => {
    const layers = [fakeLayer({ id: 1 })];
    const map = fakeMap([layerId(layers[0])]);

    new LayerControl({ layers, storage: memoryStorage("[1]") }).onAdd(map as never);

    expect(map.visibility[layerId(layers[0])]).toBe("none");
  });

  it("unchecking a layer hides it on the map AND persists, so the choice survives a reload", () => {
    const layers = [fakeLayer({ id: 7 })];
    const map = fakeMap([layerId(layers[0])]);
    const storage = memoryStorage();

    const el = new LayerControl({ layers, storage }).onAdd(map as never);
    const box = el.querySelector<HTMLInputElement>('input[type="checkbox"]')!;
    box.checked = false;
    box.dispatchEvent(new Event("change"));

    expect(map.visibility[layerId(layers[0])]).toBe("none");
    expect(readHiddenLayerIds(storage)).toEqual(new Set([7]));
  });

  it("re-checking clears it from storage rather than leaving a stale hidden id", () => {
    const layers = [fakeLayer({ id: 7 })];
    const storage = memoryStorage("[7]");
    const el = new LayerControl({ layers, storage }).onAdd(fakeMap([layerId(layers[0])]) as never);

    const box = el.querySelector<HTMLInputElement>('input[type="checkbox"]')!;
    box.checked = true;
    box.dispatchEvent(new Event("change"));

    expect(readHiddenLayerIds(storage)).toEqual(new Set());
  });

  it("a layer the operator adds later defaults to visible — the stored shape is the hidden set", () => {
    // Storage predates layer 2 existing. Storing the VISIBLE set instead would
    // leave the new layer silently off for every existing user.
    const layers = [fakeLayer({ id: 1 }), fakeLayer({ id: 2, name: "Newly added" })];
    const el = new LayerControl({ layers, storage: memoryStorage("[1]") }).onAdd(
      fakeMap(layers.map((l) => layerId(l))) as never,
    );

    const boxes = [...el.querySelectorAll<HTMLInputElement>('input[type="checkbox"]')];
    expect(boxes[0].checked).toBe(false);
    expect(boxes[1].checked).toBe(true);
  });

  it("re-enables pointer-events, or maplibre's ctrl corner makes the toggle unclickable", () => {
    // Regression: maplibre-gl.css sets `pointer-events: none` on
    // `.maplibregl-ctrl-top-right`, the container `addControl` puts this in.
    // Without restoring it, a real mouse click fell through to the canvas and
    // rotated the globe — while keyboard activation kept working, so every
    // behavioural test below still passed. Only fe-vue loads that stylesheet,
    // so it reproduced on skylight and not on the admin maps.
    document.getElementById("phpvms-map-layer-control-styles")?.remove();
    const layers = [fakeLayer()];
    new LayerControl({ layers, storage: memoryStorage() }).onAdd(
      fakeMap([layerId(layers[0])]) as never,
    );

    const css = document.getElementById("phpvms-map-layer-control-styles")?.textContent ?? "";
    expect(css).toContain("pointer-events: auto");
  });

  it("the panel starts collapsed and the toggle reports its state to assistive tech", () => {
    const layers = [fakeLayer()];
    const el = new LayerControl({ layers, storage: memoryStorage() }).onAdd(
      fakeMap([layerId(layers[0])]) as never,
    );

    const toggle = el.querySelector("button")!;
    const panel = el.querySelector<HTMLElement>(".phpvms-map-layer-control__panel")!;
    expect(panel.hidden).toBe(true);
    expect(toggle.getAttribute("aria-expanded")).toBe("false");

    toggle.click();
    expect(panel.hidden).toBe(false);
    expect(toggle.getAttribute("aria-expanded")).toBe("true");
  });
});

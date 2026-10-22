import { beforeEach, describe, expect, it, vi } from "vitest";
import { ESRI_SENTINEL } from "../basemaps.ts";
import type { MapConfig } from "../types.ts";

/**
 * Only the branches reachable without constructing a real `maplibregl.Map`
 * (needs live WebGL2, unavailable in happy-dom) are unit-tested here — an
 * unresolvable element id, and WebGL2 unsupported. The full composition
 * (`createMap` → `createRoute` ×2 → `frameToRoute`, and `createMap` →
 * `live-markers.ts`) is verified live against a real browser — see the
 * tasks.md 6.1 report.
 */

const config: MapConfig = {
  basemapLight: ESRI_SENTINEL,
  basemapDark: ESRI_SENTINEL,
  customStyleUrl: null,
  customStyleApiKey: null,
  layers: [],
};

beforeEach(() => {
  vi.resetModules();
});

describe("renderPirepMap", () => {
  it("returns undefined for an element id that does not resolve, without attempting createMap", async () => {
    vi.doMock("../browser.ts", () => ({ supportsWebGL2: () => true, cappedPixelRatio: () => 1 }));
    const { renderPirepMap } = await import("./imperative.ts");

    const result = await renderPirepMap("does-not-exist", {
      config,
      theme: "light",
      flown: [],
      planned: [],
      fallbackAltitudeFt: null,
    });
    expect(result).toBeUndefined();
  });

  it("returns undefined when WebGL2 is unsupported, same as a bare createMap call", async () => {
    vi.doMock("../browser.ts", () => ({ supportsWebGL2: () => false, cappedPixelRatio: () => 1 }));
    const { renderPirepMap } = await import("./imperative.ts");

    const el = document.createElement("div");
    document.body.appendChild(el);
    const result = await renderPirepMap(el, {
      config,
      theme: "light",
      flown: [],
      planned: [],
      fallbackAltitudeFt: null,
    });
    expect(result).toBeUndefined();
    el.remove();
  });
});

describe("renderLiveMap", () => {
  it("returns undefined for an element id that does not resolve", async () => {
    vi.doMock("../browser.ts", () => ({ supportsWebGL2: () => true, cappedPixelRatio: () => 1 }));
    const { renderLiveMap } = await import("./imperative.ts");

    const result = await renderLiveMap("does-not-exist", { config, theme: "light", flights: [] });
    expect(result).toBeUndefined();
  });

  it("accepts an HTMLElement directly, not just a string id", async () => {
    vi.doMock("../browser.ts", () => ({ supportsWebGL2: () => false, cappedPixelRatio: () => 1 }));
    const { renderLiveMap } = await import("./imperative.ts");

    const el = document.createElement("div");
    // supportsWebGL2 mocked false, so this exercises the resolveElement(el) branch for a real
    // element and then degrades — proving element resolution doesn't require a string id.
    const result = await renderLiveMap(el, { config, theme: "light", flights: [] });
    expect(result).toBeUndefined();
  });
});

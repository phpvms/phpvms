import { beforeEach, describe, expect, it, vi } from "vitest";
import { ESRI_SENTINEL } from "./basemaps.ts";
import type { MapConfig } from "./types.ts";

/**
 * Only the branches that DON'T need a real `new maplibregl.Map(...)`
 * construction (which needs a live WebGL2 context happy-dom cannot provide)
 * are exercised here: the WebGL2 gate, the context-budget gate, and reuse.
 * The full construction path (style resolution → globe projection → layers
 * → attribution) is covered live in the harness, not here — see the
 * tasks.md 3.x report.
 *
 * `vi.resetModules()` runs before EVERY test, and both `base-map.ts` AND
 * `context-budget.ts` are re-imported fresh inside each test AFTER that
 * reset — `context-budget.ts` holds module-scope state (`activeMaps`), so a
 * test that imported it once at file scope would end up asserting against a
 * DIFFERENT module instance than the one the freshly-reloaded `base-map.ts`
 * actually uses internally.
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

describe("createMap — degradation branches", () => {
  it("degrades to the static fallback when WebGL2 is unsupported, without touching context accounting", async () => {
    vi.doMock("./browser.ts", () => ({ supportsWebGL2: () => false, cappedPixelRatio: () => 1 }));
    const { createMap } = await import("./base-map.ts");
    const { existingMapFor } = await import("./context-budget.ts");

    const el = document.createElement("div");
    const result = await createMap(el, {
      config,
      theme: "light",
      fallback: { from: { icao: "KJFK" }, to: { icao: "EGLL" } },
    });

    expect(result.ok).toBe(false);
    if (!result.ok) expect(result.reason).toBe("no-webgl2");
    expect(el.textContent).toContain("KJFK");
    expect(existingMapFor(el)).toBeUndefined();
  });

  it("degrades to the static fallback when the context budget is exhausted", async () => {
    vi.doMock("./browser.ts", () => ({ supportsWebGL2: () => true, cappedPixelRatio: () => 1 }));
    const { createMap } = await import("./base-map.ts");
    const { canCreateNewContext, registerMap } = await import("./context-budget.ts");

    let created = 0;
    while (canCreateNewContext()) {
      registerMap(document.createElement("div"), {} as never);
      created++;
    }
    expect(created).toBeGreaterThan(0); // sanity: the budget is finite, so this loop must terminate

    const el = document.createElement("div");
    const result = await createMap(el, {
      config,
      theme: "light",
      fallback: { from: { icao: "KJFK" }, to: { icao: "EGLL" } },
    });
    expect(result.ok).toBe(false);
    if (!result.ok) expect(result.reason).toBe("context-budget-exceeded");
    expect(el.textContent).toContain("KJFK");
  });

  it("reuses an already-registered instance for the same element rather than degrading or re-creating", async () => {
    vi.doMock("./browser.ts", () => ({ supportsWebGL2: () => true, cappedPixelRatio: () => 1 }));
    const { createMap } = await import("./base-map.ts");
    const { registerMap } = await import("./context-budget.ts");

    const el = document.createElement("div");
    const fakeExisting = { fake: "map" };
    registerMap(el, fakeExisting as never);

    const result = await createMap(el, { config, theme: "light" });
    expect(result.ok).toBe(true);
    if (result.ok) expect(result.map).toBe(fakeExisting);
  });

  it("does not let a reuse-path owner's destroy() tear down a map another owner still holds", async () => {
    // Regression for a real bug (codex review, verified): every reuse of an already-live map used
    // to return an INDEPENDENT `destroy()` closure with no bookkeeping tying it to the original
    // owner — either one calling `destroy()` tore the shared map down (`map.remove()`)
    // unconditionally, killing it for the other. `registerMap` refcounts reuse now (base-map.ts),
    // so a non-last owner's `destroy()` must be a no-op: the map stays registered and `remove()`
    // (represented here by the spy) must not fire.
    vi.doMock("./browser.ts", () => ({ supportsWebGL2: () => true, cappedPixelRatio: () => 1 }));
    const { createMap } = await import("./base-map.ts");
    const { registerMap, existingMapFor } = await import("./context-budget.ts");

    const el = document.createElement("div");
    const remove = vi.fn();
    const fakeExisting = { fake: "map", remove };
    registerMap(el, fakeExisting as never); // simulates an earlier real construction's own registration

    const reuse = await createMap(el, { config, theme: "light" }); // a second owner, via the reuse path
    expect(reuse.ok).toBe(true);
    if (!reuse.ok) return;

    reuse.destroy(); // this owner releases its own claim only
    expect(remove).not.toHaveBeenCalled();
    expect(existingMapFor(el)).toBe(fakeExisting); // the first owner's registration is untouched
  });
});

describe("3D terrain", () => {
  /**
   * Terrain is what MapLibre constrains the camera against, so it is the half of this that makes
   * `maxPitch: 85` usable rather than a licence to tilt through the ground (design.md Non-Goal 3
   * is reversed deliberately; see `applyTerrain`).
   */
  async function createWithTerrain(terrain?: boolean) {
    vi.resetModules();

    const calls: { addSource: unknown[][]; setTerrain: unknown[][] } = {
      addSource: [],
      setTerrain: [],
    };
    let fireLoad: (() => void) | undefined;

    class FakeMap {
      addSource = vi.fn((...args: unknown[]) => calls.addSource.push(args));
      setTerrain = vi.fn((...args: unknown[]) => calls.setTerrain.push(args));
      setProjection = vi.fn();
      setPixelRatio = vi.fn();
      addControl = vi.fn();
      addLayer = vi.fn();
      getLayer = vi.fn();
      getSource = vi.fn();
      remove = vi.fn();
      resize = vi.fn();
      getCanvas = vi.fn(() => document.createElement("canvas"));
      getStyle = vi.fn(() => ({}));
      on = vi.fn((event: string, handler: () => void) => {
        if (event === "load") fireLoad = handler;
      });
      once = vi.fn((event: string, handler: () => void) => {
        if (event === "load") handler();
      });
      off = vi.fn();
    }

    vi.doMock("maplibre-gl", () => ({ Map: FakeMap, setWorkerUrl: vi.fn() }));

    const { createMap } = await import("./base-map.ts");
    const el = document.createElement("div");
    await createMap(el, { config, theme: "light", ...(terrain === undefined ? {} : { terrain }) });
    fireLoad?.();

    return calls;
  }

  it("attaches the DEM and enables terrain by default", async () => {
    const calls = await createWithTerrain();

    const dem = calls.addSource.find(
      ([, spec]) => (spec as { type?: string })?.type === "raster-dem",
    );
    expect(dem).toBeDefined();
    expect((dem?.[1] as { url?: string })?.url).toBe("https://tiles.mapterhorn.com/tilejson.json");
    expect(calls.setTerrain).toHaveLength(1);
  });

  it("skips the DEM entirely when a surface opts out", async () => {
    // A surface that never tilts should not pay for a second tile stream.
    const calls = await createWithTerrain(false);

    expect(
      calls.addSource.find(([, spec]) => (spec as { type?: string })?.type === "raster-dem"),
    ).toBeUndefined();
    expect(calls.setTerrain).toHaveLength(0);
  });

  it("raises maxPitch past MapLibre's default 60 so the camera can tilt to the horizon", async () => {
    vi.resetModules();
    let options: { maxPitch?: number } | undefined;

    class FakeMap {
      constructor(opts: { maxPitch?: number }) {
        options = opts;
      }
      setPixelRatio = vi.fn();
      on = vi.fn();
      once = vi.fn();
      off = vi.fn();
      resize = vi.fn();
      remove = vi.fn();
      getCanvas = vi.fn(() => document.createElement("canvas"));
    }

    vi.doMock("maplibre-gl", () => ({ Map: FakeMap, setWorkerUrl: vi.fn() }));
    const { createMap } = await import("./base-map.ts");
    await createMap(document.createElement("div"), { config, theme: "light" });

    expect(options?.maxPitch).toBe(85);
  });
});

describe("maplibre worker URL", () => {
  /**
   * In dev, Vite hands back a ROOT-RELATIVE worker path (`/@fs/...`). The app is served from the
   * Laravel host while Vite serves modules from its own origin, so passing that through unchanged
   * made the browser resolve it against the APP host and load its HTML 404 page as a worker
   * ("blocked because of a disallowed MIME type (text/html)"). Seen in a real browser console.
   */
  it("hands maplibre an absolute URL, not the bundler's relative one", async () => {
    vi.resetModules();

    const setWorkerUrl = vi.fn();
    class FakeMap {}
    vi.doMock("maplibre-gl", () => ({ Map: FakeMap, setWorkerUrl }));

    await import("./base-map.ts");

    expect(setWorkerUrl).toHaveBeenCalledTimes(1);
    const passed = setWorkerUrl.mock.calls[0][0] as string;
    expect(() => new URL(passed)).not.toThrow(); // absolute — parses with no base
    expect(passed.startsWith("/")).toBe(false);
  });
});

describe("createMap — v6 GPUInitializationError from the constructor", () => {
  /**
   * maplibre v6.7.0 THROWS `GPUInitializationError` out of the `Map`
   * constructor when the WebGL2 context cannot be created. v5 fired an `error`
   * event no listener could catch and handed back a partially built map, so
   * this path simply did not exist before the upgrade. `supportsWebGL2()`
   * screens the common case, but a GPU reset or a driver-level context limit
   * still reaches the constructor.
   */
  it("degrades to the static fallback and releases the reserved context slot", async () => {
    vi.resetModules();

    class ThrowingMap {
      constructor() {
        throw new Error("GPUInitializationError: could not create WebGL2 context");
      }
    }

    vi.doMock("maplibre-gl", () => ({ Map: ThrowingMap, setWorkerUrl: vi.fn() }));

    const { createMap } = await import("./base-map.ts");
    const { activeContextCount } = await import("./context-budget.ts");

    const before = activeContextCount();
    const el = document.createElement("div");
    const result = await createMap(el, { config, theme: "light" });

    expect(result.ok).toBe(false);
    if (result.ok) return;
    expect(result.reason).toBe("gpu-init-failed");

    // The slot is reserved BEFORE the constructor runs; without an explicit release on this
    // path it leaks and permanently shrinks the budget for every later map on the page.
    expect(activeContextCount()).toBe(before);
  });
});

describe("createMap — layer control teardown race", () => {
  /**
   * The layer control is loaded by a dynamic `import()` (it is kept out of the
   * eager chunk, `tiering.build.test.ts`). That import can still be in flight
   * when the map is destroyed — navigating away mid-load is enough.
   *
   * `Map#remove()` does `delete this.style` and `Map#getLayer` is
   * `return this.style.getLayer(e)`, so adding the control to a torn-down map
   * throws "Cannot read properties of undefined (reading 'getLayer')" out of
   * `onAdd`'s `applyLayerVisibility`. Seen in a real browser console on a
   * live-map page, then confirmed against maplibre-gl's own source.
   */
  it("does not add the control when the map was destroyed while the import was in flight", async () => {
    const addControl = vi.fn();
    let fireLoad: (() => void) | undefined;

    class FakeMap {
      addControl = addControl;
      setPixelRatio = vi.fn();
      setProjection = vi.fn();
      remove = vi.fn();
      resize = vi.fn();
      addSource = vi.fn();
      addLayer = vi.fn();
      getLayer = vi.fn();
      getSource = vi.fn();
      on = vi.fn((event: string, cb: () => void) => {
        if (event === "load") fireLoad = cb;
      });
      once = vi.fn();
    }

    vi.doMock("./browser.ts", () => ({ supportsWebGL2: () => true, cappedPixelRatio: () => 1 }));
    vi.doMock("./style.ts", () => ({
      resolveStyle: async () => ({ version: 8, name: "t", sources: {}, layers: [] }),
    }));
    // v6 is ESM-only with no default export — mock the named `Map`, as base-map.ts imports it.
    vi.doMock("maplibre-gl", () => ({ Map: FakeMap, setWorkerUrl: vi.fn() }));

    const { createMap } = await import("./base-map.ts");
    const el = document.createElement("div");

    const result = await createMap(el, {
      // A non-empty layer list is what makes createMap load the control at all.
      config: {
        ...config,
        layers: [
          {
            id: 1,
            name: "L",
            type: "raster",
            urlTemplate: "https://e.test/{z}/{x}/{y}.png",
            attribution: null,
            minZoom: 0,
            maxZoom: 22,
            opacity: 1,
            apiKey: null,
            surfaces: null,
          },
        ],
      },
      theme: "light",
    });

    expect(result.ok).toBe(true);
    if (!result.ok) return;

    fireLoad?.(); // starts the dynamic import
    result.destroy(); // ...and the map goes away before it resolves

    // 50ms, not a `setTimeout(0)` tick: the dynamic `import()` needs more than
    // one macrotask to resolve here, and with a 0ms wait this assertion passed
    // whether or not the guard existed — a vacuous pass. Confirmed by removing
    // the guard and watching this test go green anyway.
    await new Promise((resolve) => setTimeout(resolve, 50));

    expect(addControl).not.toHaveBeenCalled();
  });

  it("POSITIVE CONTROL: adds the control when the map is still alive", async () => {
    const addControl = vi.fn();
    let fireLoad: (() => void) | undefined;

    class FakeMap {
      addControl = addControl;
      setPixelRatio = vi.fn();
      setProjection = vi.fn();
      remove = vi.fn();
      resize = vi.fn();
      addSource = vi.fn();
      addLayer = vi.fn();
      getLayer = vi.fn();
      getSource = vi.fn();
      on = vi.fn((event: string, cb: () => void) => {
        if (event === "load") fireLoad = cb;
      });
      once = vi.fn();
    }

    vi.doMock("./browser.ts", () => ({ supportsWebGL2: () => true, cappedPixelRatio: () => 1 }));
    vi.doMock("./style.ts", () => ({
      resolveStyle: async () => ({ version: 8, name: "t", sources: {}, layers: [] }),
    }));
    // v6 is ESM-only with no default export — mock the named `Map`, as base-map.ts imports it.
    vi.doMock("maplibre-gl", () => ({ Map: FakeMap, setWorkerUrl: vi.fn() }));

    const { createMap } = await import("./base-map.ts");
    const el = document.createElement("div");

    const result = await createMap(el, {
      config: {
        ...config,
        layers: [
          {
            id: 1,
            name: "L",
            type: "raster",
            urlTemplate: "https://e.test/{z}/{x}/{y}.png",
            attribution: null,
            minZoom: 0,
            maxZoom: 22,
            opacity: 1,
            apiKey: null,
            surfaces: null,
          },
        ],
      },
      theme: "light",
    });
    expect(result.ok).toBe(true);

    fireLoad?.();
    await new Promise((resolve) => setTimeout(resolve, 50));

    expect(addControl).toHaveBeenCalled();
  });
});

import { describe, expect, it, vi } from "vitest";
import { createAnchoredCustomLayer } from "./anchored-scene.ts";

/**
 * Exercises `createAnchoredCustomLayer`'s rebuild/dispose lifecycle (design.md
 * D2/D4, tasks.md 2.2/2.7) through its real `render()` path. `THREE.WebGLRenderer`
 * is mocked — it needs a live WebGL2 context happy-dom does not provide — but
 * every other THREE export, and all of `anchored-scene.ts`'s own logic
 * (dirty tracking, rebuild-on-`setChunks`, rebuild-on-mirroring-flip, dispose
 * ordering), is real.
 */
vi.mock("three", async (importOriginal) => {
  const actual = await importOriginal<typeof import("three")>();
  return {
    ...actual,
    // A regular function, not an arrow function: `new THREE.WebGLRenderer(...)` requires a
    // constructible mock, and arrow functions can never be called with `new`.
    WebGLRenderer: vi.fn().mockImplementation(function FakeWebGLRenderer() {
      return {
        autoClear: false,
        resetState: vi.fn(),
        render: vi.fn(),
        dispose: vi.fn(),
      };
    }),
  };
});

function fakeMap(triggerRepaint = vi.fn()) {
  return {
    getCanvas: () => ({ clientWidth: 800, clientHeight: 600 }),
    transform: {
      getMatrixForModel: () => new Float32Array(16).fill(0).map((_, i) => (i % 5 === 0 ? 1 : 0)),
    },
    triggerRepaint,
  } as unknown as Parameters<typeof createAnchoredCustomLayer>[1];
}

/** `document.hidden` is a read-only getter; happy-dom allows redefining it for this exact case. */
function setDocumentHidden(hidden: boolean) {
  Object.defineProperty(document, "hidden", { value: hidden, configurable: true });
}

/**
 * The real type (`CustomRenderMethodInput`) carries several fields this test
 * never reads (`farZ`, `fov`, ...); only `defaultProjectionData` matters to
 * `anchored-scene.ts`'s own logic, so the fake supplies just that.
 */
function frameArgs(
  transition = 0,
): Parameters<ReturnType<typeof createAnchoredCustomLayer>["render"]>[1] {
  return {
    defaultProjectionData: {
      mainMatrix: Array.from<number>({ length: 16 }).fill(0),
      projectionTransition: transition,
    },
  } as unknown as Parameters<ReturnType<typeof createAnchoredCustomLayer>["render"]>[1];
}

describe("createAnchoredCustomLayer rebuild/dispose", () => {
  it("builds nothing until onAdd + a render pass, then builds on the first render after setChunks", () => {
    const map = fakeMap();
    const buildChunk = vi
      .fn()
      .mockImplementation((anchor) => ({ anchor, render: vi.fn(), dispose: vi.fn() }));
    const layer = createAnchoredCustomLayer("test", map, { buildChunk });

    layer.setChunks([{ anchor: { lon: 0, lat: 0, altitude: 0 }, points: [] }]);
    expect(buildChunk).not.toHaveBeenCalled(); // deferred until render()

    layer.onAdd?.(map, {} as WebGL2RenderingContext);
    layer.render({} as WebGL2RenderingContext, frameArgs());

    expect(buildChunk).toHaveBeenCalledTimes(1);
    expect(layer.chunkCount()).toBe(1);
  });

  it("setChunks disposes the previous chunks' handles and builds fresh ones — no incremental append", () => {
    const map = fakeMap();
    const disposeSpies: ReturnType<typeof vi.fn>[] = [];
    const buildChunk = vi.fn().mockImplementation((anchor) => {
      const dispose = vi.fn();
      disposeSpies.push(dispose);
      return { anchor, render: vi.fn(), dispose };
    });
    const layer = createAnchoredCustomLayer("test", map, { buildChunk });
    layer.onAdd?.(map, {} as WebGL2RenderingContext);

    layer.setChunks([{ anchor: { lon: 0, lat: 0, altitude: 0 }, points: [1] }]);
    layer.render({} as WebGL2RenderingContext, frameArgs());
    expect(layer.chunkCount()).toBe(1);
    expect(disposeSpies[0]).not.toHaveBeenCalled();

    // A second setData-style call with a DIFFERENT chunk set.
    layer.setChunks([
      { anchor: { lon: 1, lat: 1, altitude: 0 }, points: [1] },
      { anchor: { lon: 2, lat: 2, altitude: 0 }, points: [2] },
    ]);
    layer.render({} as WebGL2RenderingContext, frameArgs());

    // The FIRST chunk's handle was disposed before the new ones were drawn.
    expect(disposeSpies[0]).toHaveBeenCalledTimes(1);
    expect(layer.chunkCount()).toBe(2);
  });

  it("an empty point set disposes the previous geometry and draws nothing", () => {
    const map = fakeMap();
    const dispose = vi.fn();
    const buildChunk = vi
      .fn()
      .mockImplementation((anchor) => ({ anchor, render: vi.fn(), dispose }));
    const layer = createAnchoredCustomLayer("test", map, { buildChunk });
    layer.onAdd?.(map, {} as WebGL2RenderingContext);

    layer.setChunks([{ anchor: { lon: 0, lat: 0, altitude: 0 }, points: [1] }]);
    layer.render({} as WebGL2RenderingContext, frameArgs());
    expect(layer.chunkCount()).toBe(1);

    layer.setChunks([]);
    layer.render({} as WebGL2RenderingContext, frameArgs());
    expect(dispose).toHaveBeenCalledTimes(1);
    expect(layer.chunkCount()).toBe(0);
  });

  it("re-bakes (disposes + rebuilds) every chunk when the globe/mercator mirroring flips", () => {
    const map = fakeMap();
    const disposeSpies: ReturnType<typeof vi.fn>[] = [];
    const buildChunk = vi.fn().mockImplementation((anchor) => {
      const dispose = vi.fn();
      disposeSpies.push(dispose);
      return { anchor, render: vi.fn(), dispose };
    });
    const layer = createAnchoredCustomLayer("test", map, { buildChunk });
    layer.onAdd?.(map, {} as WebGL2RenderingContext);
    layer.setChunks([{ anchor: { lon: 0, lat: 0, altitude: 0 }, points: [1] }]);

    layer.render({} as WebGL2RenderingContext, frameArgs(0)); // pure mercator
    expect(buildChunk).toHaveBeenCalledTimes(1);
    expect(disposeSpies[0]).not.toHaveBeenCalled();

    layer.render({} as WebGL2RenderingContext, frameArgs(1)); // flips to globe — must re-bake
    expect(disposeSpies[0]).toHaveBeenCalledTimes(1);
    expect(buildChunk).toHaveBeenCalledTimes(2);

    layer.render({} as WebGL2RenderingContext, frameArgs(1)); // still globe — no further rebuild
    expect(buildChunk).toHaveBeenCalledTimes(2);
  });

  it("disposeChunks disposes every current handle without needing onRemove", () => {
    const map = fakeMap();
    const dispose = vi.fn();
    const buildChunk = vi
      .fn()
      .mockImplementation((anchor) => ({ anchor, render: vi.fn(), dispose }));
    const layer = createAnchoredCustomLayer("test", map, { buildChunk });
    layer.onAdd?.(map, {} as WebGL2RenderingContext);
    layer.setChunks([{ anchor: { lon: 0, lat: 0, altitude: 0 }, points: [1] }]);
    layer.render({} as WebGL2RenderingContext, frameArgs());

    layer.disposeChunks();
    expect(dispose).toHaveBeenCalledTimes(1);
    expect(layer.chunkCount()).toBe(0);
  });

  it("keeps triggering a repaint mid-blend while the page is visible (tasks.md 2b.4)", () => {
    const triggerRepaint = vi.fn();
    const map = fakeMap(triggerRepaint);
    setDocumentHidden(false);
    const layer = createAnchoredCustomLayer("test", map, {
      buildChunk: (anchor) => ({ anchor, render: vi.fn(), dispose: vi.fn() }),
    });
    layer.onAdd?.(map, {} as WebGL2RenderingContext);
    layer.setChunks([{ anchor: { lon: 0, lat: 0, altitude: 0 }, points: [1] }]);

    layer.render({} as WebGL2RenderingContext, frameArgs(0.5)); // mid-blend
    expect(triggerRepaint).toHaveBeenCalledTimes(1);
  });

  it("does NOT trigger a repaint mid-blend while the page is hidden (tasks.md 2b.4)", () => {
    const triggerRepaint = vi.fn();
    const map = fakeMap(triggerRepaint);
    setDocumentHidden(true);
    const layer = createAnchoredCustomLayer("test", map, {
      buildChunk: (anchor) => ({ anchor, render: vi.fn(), dispose: vi.fn() }),
    });
    layer.onAdd?.(map, {} as WebGL2RenderingContext);
    layer.setChunks([{ anchor: { lon: 0, lat: 0, altitude: 0 }, points: [1] }]);

    layer.render({} as WebGL2RenderingContext, frameArgs(0.5)); // mid-blend, but hidden
    expect(triggerRepaint).not.toHaveBeenCalled();

    setDocumentHidden(false); // restore for any test that runs after this one
  });

  it("never triggers a repaint outside a mid-blend frame, hidden or not", () => {
    const triggerRepaint = vi.fn();
    const map = fakeMap(triggerRepaint);
    setDocumentHidden(false);
    const layer = createAnchoredCustomLayer("test", map, {
      buildChunk: (anchor) => ({ anchor, render: vi.fn(), dispose: vi.fn() }),
    });
    layer.onAdd?.(map, {} as WebGL2RenderingContext);
    layer.setChunks([{ anchor: { lon: 0, lat: 0, altitude: 0 }, points: [1] }]);

    layer.render({} as WebGL2RenderingContext, frameArgs(0)); // pure mercator, not mid-blend
    layer.render({} as WebGL2RenderingContext, frameArgs(1)); // pure globe, not mid-blend
    expect(triggerRepaint).not.toHaveBeenCalled();
  });
});

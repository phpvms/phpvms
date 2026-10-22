import { beforeEach, describe, expect, it, vi } from "vitest";
import { createApp, defineComponent, h, nextTick, ref } from "vue";
import { ESRI_SENTINEL } from "../../basemaps.ts";
import type { MapConfig } from "../../types.ts";

/**
 * `onMounted`/`onUnmounted` only register against an ACTIVE component
 * instance, so `useMap` is exercised through a real `createApp(...).mount()`
 * on a detached DOM node — no `@vue/test-utils` needed for that, just `vue`
 * itself (already a peer dependency). `.unmount()` is what proves the
 * critical guarantee (cleanup on unmount, tasks.md 7.1) actually fires.
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

function mountWithUseMap(
  useMapFn: (typeof import("./useMap.ts"))["useMap"],
  resolvedFlag: { destroyed: boolean },
) {
  const container = document.createElement("div");
  document.body.appendChild(container);

  let captured: ReturnType<typeof useMapFn> | undefined;
  const App = defineComponent({
    setup() {
      const el = ref<HTMLElement>();
      captured = useMapFn(el, { config, theme: "light" });
      return () => h("div", { ref: el });
    },
  });

  const app = createApp(App);
  app.mount(container);
  return {
    captured: captured!,
    unmount: () => {
      app.unmount();
      container.remove();
      resolvedFlag.destroyed = true;
    },
  };
}

describe("useMap", () => {
  it("calls createMap once the template ref resolves, and exposes the map + ready state", async () => {
    vi.doMock("../../browser.ts", () => ({
      supportsWebGL2: () => false,
      cappedPixelRatio: () => 1,
    }));
    const { useMap } = await import("./useMap.ts");

    const { captured, unmount } = mountWithUseMap(useMap, { destroyed: false });
    await nextTick();
    await nextTick(); // one tick for the ref to resolve, one for createMap's own await chain

    // WebGL2 unsupported, so createMap degrades — `ready` stays false, matching a real failure path.
    expect(captured.ready.value).toBe(false);
    expect(captured.map.value).toBeUndefined();
    unmount();
  });

  it("calls the createMap handle's destroy() on unmount — the SPA leak guarantee (tasks.md 7.1)", async () => {
    const destroy = vi.fn();
    vi.doMock("../../browser.ts", () => ({
      supportsWebGL2: () => true,
      cappedPixelRatio: () => 1,
    }));
    // `loaded: () => true` stands in for `whenLoaded`'s `map.loaded()` short-circuit (base-map.ts)
    // — the style is already loaded, so `useMap` doesn't need a real `map.once("load")` to await.
    vi.doMock("../../base-map.ts", () => ({
      createMap: vi.fn().mockResolvedValue({
        ok: true,
        map: { fake: "map", loaded: () => true },
        destroy,
      }),
      whenLoaded: async (map: { loaded(): boolean }) => {
        if (!map.loaded()) throw new Error("test double only supports the already-loaded path");
      },
    }));
    const { useMap } = await import("./useMap.ts");

    const { captured, unmount } = mountWithUseMap(useMap, { destroyed: false });
    await nextTick();
    await nextTick();
    await nextTick(); // one more tick for useMap's own await whenLoaded(...) continuation

    expect(captured.ready.value).toBe(true);
    expect(captured.map.value).toEqual({ fake: "map", loaded: expect.any(Function) });
    expect(destroy).not.toHaveBeenCalled();

    unmount();
    expect(destroy).toHaveBeenCalledTimes(1);
    expect(captured.map.value).toBeUndefined();
    expect(captured.ready.value).toBe(false);
  });

  it("destroys a handle that resolves while unmounting was already in flight (codex review, verified)", async () => {
    // Regression: unmount can race the FIRST await (`createMap` itself), not just `whenLoaded`
    // — before this fix, `destroy` was only ever assigned AFTER `createMap` resolved, so an
    // unmount that landed before that point found nothing to call, and the eventual handle
    // (once `createMap` did resolve) was stored into `destroy` with nothing left to invoke it,
    // leaking the WebGL context and its context-budget registration.
    const destroy = vi.fn();
    let resolveCreateMap!: (result: unknown) => void;
    vi.doMock("../../browser.ts", () => ({
      supportsWebGL2: () => true,
      cappedPixelRatio: () => 1,
    }));
    vi.doMock("../../base-map.ts", () => ({
      createMap: vi.fn(
        () =>
          new Promise((resolve) => {
            resolveCreateMap = resolve;
          }),
      ),
      whenLoaded: vi.fn().mockResolvedValue(undefined),
    }));
    const { useMap } = await import("./useMap.ts");

    const { captured, unmount } = mountWithUseMap(useMap, { destroyed: false });
    await nextTick();
    await nextTick(); // one tick for the ref to resolve — createMap() is now pending, unresolved

    unmount(); // races the still-pending createMap() call
    resolveCreateMap({ ok: true, map: { fake: "map", loaded: () => true }, destroy });
    await nextTick();
    await nextTick();
    await nextTick();

    expect(destroy).toHaveBeenCalledTimes(1);
    expect(captured.map.value).toBeUndefined();
    expect(captured.ready.value).toBe(false);
  });
});

import { beforeEach, describe, expect, it, vi } from "vitest";
import { createApp, defineComponent, h, nextTick, type Ref, ref } from "vue";
import type { RoutePoint } from "../../types.ts";

beforeEach(() => {
  vi.resetModules();
});

/**
 * A single `nextTick()` flushes Vue's OWN reactivity queue, but `useRoute`'s
 * watcher callback is itself `async` (awaits `createRoute(...)`, a mocked
 * promise) — settling that needs a macrotask, not just Vue's microtask
 * queue, so this drains both rather than guessing a fixed `nextTick()` count.
 */
async function flushAsync(): Promise<void> {
  await nextTick();
  await new Promise((resolve) => setTimeout(resolve, 0));
  await nextTick();
}

function mountWithUseRoute(
  useRouteFn: (typeof import("./useRoute.ts"))["useRoute"],
  map: Ref<unknown>,
  points: Ref<RoutePoint[]>,
) {
  const container = document.createElement("div");
  document.body.appendChild(container);
  const App = defineComponent({
    setup() {
      useRouteFn(map as never, 1, points);
      return () => h("div");
    },
  });
  const app = createApp(App);
  app.mount(container);
  return () => {
    app.unmount();
    container.remove();
  };
}

describe("useRoute", () => {
  it("creates the layer once `map` becomes available and applies the current points", async () => {
    const setData = vi.fn();
    const dispose = vi.fn();
    const createRoute = vi.fn().mockResolvedValue({ setData, dispose });
    vi.doMock("../../index.ts", () => ({ createRoute }));
    const { useRoute } = await import("./useRoute.ts");

    const map = ref<unknown>(undefined);
    const points = ref<RoutePoint[]>([{ lat: 1, lon: 2, altitude: 0 }]);
    const unmount = mountWithUseRoute(useRoute, map, points);

    map.value = { fake: "map", on: vi.fn(), off: vi.fn() };
    await flushAsync();

    expect(createRoute).toHaveBeenCalledWith(map.value, 1, {});
    expect(setData).toHaveBeenCalledWith(points.value);
    unmount();
  });

  it("calls setData again when points changes, without recreating the layer", async () => {
    const setData = vi.fn();
    const createRoute = vi.fn().mockResolvedValue({ setData, dispose: vi.fn() });
    vi.doMock("../../index.ts", () => ({ createRoute }));
    const { useRoute } = await import("./useRoute.ts");

    const map = ref<unknown>({ fake: "map", on: vi.fn(), off: vi.fn() });
    const points = ref<RoutePoint[]>([{ lat: 1, lon: 2, altitude: 0 }]);
    const unmount = mountWithUseRoute(useRoute, map, points);
    await flushAsync();

    points.value = [{ lat: 3, lon: 4, altitude: 100 }];
    await nextTick();

    expect(createRoute).toHaveBeenCalledTimes(1); // still just the one layer
    expect(setData).toHaveBeenLastCalledWith(points.value);
    unmount();
  });

  it("disposes the layer on unmount", async () => {
    const dispose = vi.fn();
    const createRoute = vi.fn().mockResolvedValue({ setData: vi.fn(), dispose });
    vi.doMock("../../index.ts", () => ({ createRoute }));
    const { useRoute } = await import("./useRoute.ts");

    const map = ref<unknown>({ fake: "map", on: vi.fn(), off: vi.fn() });
    const points = ref<RoutePoint[]>([]);
    const unmount = mountWithUseRoute(useRoute, map, points);
    await flushAsync();

    unmount();
    expect(dispose).toHaveBeenCalledTimes(1);
  });

  it("disposes a layer built while unmounting was in flight, instead of leaking it (codex review, verified)", async () => {
    // Regression: `createRoute` doesn't resolve synchronously (a real Tier 1 call dynamically
    // imports route-line.ts). If the component unmounts WHILE that's pending, the old code found
    // `layer` still unset (onUnmounted's own `layer?.dispose()` was a no-op) and then, once the
    // await resolved, went on to assign the newly-built layer anyway — never disposed, and
    // potentially installed on a map that's already been torn down.
    let resolveCreateRoute!: (layer: { setData: () => void; dispose: () => void }) => void;
    const dispose = vi.fn();
    const createRoute = vi.fn(
      () =>
        new Promise((resolve) => {
          resolveCreateRoute = resolve;
        }),
    );
    vi.doMock("../../index.ts", () => ({ createRoute }));
    const { useRoute } = await import("./useRoute.ts");

    const map = ref<unknown>(undefined);
    const points = ref<RoutePoint[]>([]);
    const unmount = mountWithUseRoute(useRoute, map, points);

    map.value = { fake: "map", on: vi.fn(), off: vi.fn() };
    await nextTick(); // let the watcher fire and call createRoute(), but it never resolves yet

    unmount(); // races the still-pending createRoute() call
    resolveCreateRoute({ setData: vi.fn(), dispose });
    await flushAsync();

    expect(dispose).toHaveBeenCalledTimes(1);
  });

  it("disposes the layer when map becomes undefined, without waiting for unmount", async () => {
    const dispose = vi.fn();
    const createRoute = vi.fn().mockResolvedValue({ setData: vi.fn(), dispose });
    vi.doMock("../../index.ts", () => ({ createRoute }));
    const { useRoute } = await import("./useRoute.ts");

    const map = ref<unknown>({ fake: "map", on: vi.fn(), off: vi.fn() });
    const points = ref<RoutePoint[]>([]);
    const unmount = mountWithUseRoute(useRoute, map, points);
    await flushAsync();

    map.value = undefined;
    await nextTick();
    expect(dispose).toHaveBeenCalledTimes(1);
    unmount();
  });
});

import { onUnmounted, type Ref, watch } from "vue";
import type { Map as MapLibreMapType } from "maplibre-gl";
import { createRoute, type CreateRouteOptions, type RouteLayer } from "../../index.ts";
import type { RoutePoint } from "../../types.ts";

/**
 * Draw a route on a map created by `useMap`, at an explicit tier (design.md
 * D13 — never inferred; the caller states it, same as the imperative
 * adapter). `tier` and `options` are read once, at the point `map` first
 * becomes available — changing them after that has no effect, matching
 * `createRoute`'s own one-shot construction; only `points` is reactive
 * (`setData`, design.md D4 — wholesale rebuild, never an incremental append).
 *
 * Disposes the route layer whenever `map` goes away (the composable's own
 * unmount, or the host clearing its `map` ref for some other reason) so a
 * route layer never outlives the map instance it was drawn on.
 */
export function useRoute(
  map: Ref<MapLibreMapType | undefined>,
  tier: 0 | 1,
  points: Ref<RoutePoint[]>,
  options: CreateRouteOptions = {},
): void {
  let layer: RouteLayer | undefined;
  let creating: Promise<RouteLayer> | undefined;
  let boundMap: MapLibreMapType | undefined;
  // Bumped on every `map` transition AND on unmount, and captured at the start of each watcher
  // invocation — the async continuation below only commits its result if its own capture still
  // matches. Confirmed a real bug (codex review, verified): without this, unmounting (or `map`
  // changing again) while `ensureLayer`'s `await createRoute(...)` was still pending left nothing
  // to clean up (onUnmounted's own `layer?.dispose()` ran while `layer` was still unset), and the
  // continuation then went on to install a layer on a map that may already be destroyed.
  let generation = 0;

  const handleCameraMove = () => layer?.updateLod?.();

  function attachLod(mapInstance: MapLibreMapType) {
    mapInstance.on("zoomend", handleCameraMove);
    mapInstance.on("moveend", handleCameraMove);
    boundMap = mapInstance;
  }

  function detachLod() {
    if (!boundMap) return;
    boundMap.off("zoomend", handleCameraMove);
    boundMap.off("moveend", handleCameraMove);
    boundMap = undefined;
  }

  async function ensureLayer(mapInstance: MapLibreMapType): Promise<RouteLayer> {
    if (!creating) creating = createRoute(mapInstance, tier, options);
    return creating;
  }

  const stopMap = watch(
    map,
    async (mapInstance) => {
      const thisGeneration = ++generation;
      if (mapInstance) {
        const created = await ensureLayer(mapInstance);
        if (thisGeneration !== generation) {
          // A newer `map` transition (or unmount) raced this one — this layer belongs to a
          // `map` value that is no longer current. Dispose it rather than installing it (or
          // leaking it unassigned, which is what happened before this fix).
          created.dispose();
          return;
        }
        layer = created;
        layer.setData(points.value);
        attachLod(mapInstance);
        return;
      }
      detachLod();
      layer?.dispose();
      layer = undefined;
      creating = undefined;
    },
    { immediate: true },
  );

  const stopPoints = watch(points, (nextPoints) => {
    layer?.setData(nextPoints);
  });

  onUnmounted(() => {
    generation++; // invalidate any in-flight ensureLayer continuation
    detachLod();
    stopMap();
    stopPoints();
    layer?.dispose();
    layer = undefined;
  });
}

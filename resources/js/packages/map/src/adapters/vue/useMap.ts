import { onUnmounted, shallowRef, type Ref, type ShallowRef, watch } from "vue";
import type { Map as MapLibreMapType } from "maplibre-gl";
import { createMap, type CreateMapOptions, whenLoaded } from "../../base-map.ts";

/**
 * The Vue adapter (design.md D1, tasks.md 7.1) — a composable, not a
 * component: `useMap` is Inertia consumers' equivalent of the imperative
 * adapter's `renderPirepMap`/`renderLiveMap`, built on the SAME `createMap`
 * (D6 — config is always a parameter here too, delivered through Inertia
 * shared props by whichever component calls this, not fetched by the
 * composable itself).
 *
 * The critical guarantee task 7.1 asks for: cleanup on unmount. `onUnmounted`
 * calls the `createMap` handle's `destroy()`, which frees the WebGL context
 * AND calls `unregisterMap` (`context-budget.ts`) — the SPA case that module
 * exists for. Without this, navigating away from a page with a map (an
 * Inertia visit, not a full page reload) would leak a live context on every
 * visit, eventually hitting the package's own `MAX_CONTEXTS` cap for reasons
 * that have nothing to do with how many maps are actually on screen.
 */

export type UseMapReturn = {
  map: ShallowRef<MapLibreMapType | undefined>;
  /**
   * `true` once `createMap` resolved successfully AND its style has finished loading. Stays
   * `false` on the WebGL2/context-budget fallback paths. Consumers such as `useRoute` rely on
   * `map` never being exposed before this — `createMap` itself resolves as soon as the `Map` is
   * constructed, not once it is safe to add a layer to (`base-map.ts`'s `whenLoaded`).
   */
  ready: Ref<boolean>;
};

/**
 * `el` is a template ref — `undefined` until the element mounts, which is
 * exactly the signal this composable waits on rather than assuming
 * `onMounted` timing lines up with the ref already being set.
 */
export function useMap(el: Ref<HTMLElement | undefined>, options: CreateMapOptions): UseMapReturn {
  const map = shallowRef<MapLibreMapType>();
  const ready = shallowRef(false);
  let destroy: (() => void) | undefined;
  // Bumped on unmount and captured at the start of the watcher's async continuation — only a
  // continuation whose own capture still matches is allowed to act. Confirmed a real bug (codex
  // review, verified) beyond what an earlier pass at this file handled: unmounting while
  // `createMap(...)` itself was still pending (i.e. BEFORE `destroy` gets assigned at all) left
  // the eventual `result.destroy` handle orphaned — nothing had it yet to call, and nothing
  // would ever call it, leaking the WebGL context and its context-budget registration. A single
  // boolean flag caught the LATER race (unmount during `whenLoaded`, after `destroy` was already
  // stored) but not this earlier one; the generation counter covers both with one mechanism.
  let generation = 0;

  const stopWatch = watch(
    el,
    async (element) => {
      if (!element || destroy) return; // already created for this composable instance
      const thisGeneration = ++generation;
      const result = await createMap(element, options);
      if (thisGeneration !== generation) {
        // Unmounted while `createMap` was pending — nothing exposed this handle yet, so it must
        // be torn down here or it leaks with nothing left to call its `destroy()`.
        if (result.ok) result.destroy();
        return;
      }
      if (!result.ok) return;
      destroy = result.destroy;
      await whenLoaded(result.map);
      if (thisGeneration !== generation) {
        // Unmounted while `whenLoaded` was pending — `destroy` was already stored, but
        // `onUnmounted` already ran and called it (as a no-op, since it wasn't set yet then).
        destroy();
        destroy = undefined;
        return;
      }
      map.value = result.map;
      ready.value = true;
    },
    { immediate: true },
  );

  onUnmounted(() => {
    generation++; // invalidate any in-flight createMap/whenLoaded continuation
    stopWatch();
    destroy?.();
    map.value = undefined;
    ready.value = false;
  });

  return { map, ready };
}

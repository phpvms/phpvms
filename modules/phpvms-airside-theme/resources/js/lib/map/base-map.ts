// v6 is ESM-only and drops the default export — named import (v5-to-v6 migration guide).
import { Map as MapLibreMap, setWorkerUrl } from "maplibre-gl";
/**
 * v6 loads its worker from a REAL URL instead of inlining it as a blob, and it
 * builds that URL as `new URL(`./${runtimeFilename}`, ...)` — a runtime
 * template, which Vite cannot statically analyse and therefore never emits. The
 * bundle then requests `assets/maplibre-gl-worker.mjs`, the server answers with
 * its HTML 404 page, and the browser refuses it: "Loading Worker ... was
 * blocked because of a disallowed MIME type (text/html)". Seen in a real
 * browser console after the v5→v6 upgrade, not anticipated.
 *
 * `?worker&url`, NOT plain `?url`. `?url` treats the file as an opaque asset
 * and copies it verbatim — including its own bare `import ... from
 * "./maplibre-gl-shared.mjs"`, a sibling Vite never emitted. The worker then
 * failed on that import instead ("NS_ERROR_CORRUPTED_CONTENT" — the 404 HTML
 * page again, this time as a module). `?worker&url` makes Vite BUNDLE the
 * worker with its dependencies and hand back a URL to the bundled file, so it
 * has no unresolved siblings. `setWorkerUrl` (a v6 public export) then points
 * maplibre at it.
 */
import maplibreWorkerUrl from "maplibre-gl/dist/maplibre-gl-worker.mjs?worker&url";
import type { Map as MapLibreMapType, StyleSpecification } from "maplibre-gl";
import { renderAttribution } from "./attribution.ts";
import { MAPTERHORN_TERRAIN_URL } from "./basemaps.ts";
import { cappedPixelRatio, supportsWebGL2 } from "./browser.ts";
import {
  canCreateNewContext,
  existingMapFor,
  registerMap,
  releaseContextSlot,
  reserveContextSlot,
  unregisterMap,
} from "./context-budget.ts";
import { type FallbackRouteSummary, renderStaticFallback } from "./fallback.ts";
import { applyLayers } from "./layers.ts";
import { resolveStyle } from "./style.ts";
import type { MapConfig, MapTheme } from "./types.ts";

/**
 * `createMap(el, { config, theme })` (design.md D6, tasks.md 3.1): config is
 * always a constructor parameter, never a module constant — the seam exists
 * from the first commit even where the values in it are still defaults.
 *
 * Ties together: WebGL2 capability (design.md D16 — an unsupported device
 * gets the static fallback, not a degraded map), cross-instance context
 * accounting (`context-budget.ts`, tasks.md 2b.5), lazy style resolution
 * (`style.ts`, never at module scope), globe projection, configured overlay
 * layers, and a combined attribution surface — all the pieces phase 2/2b
 * built standalone.
 */

const ATTRIBUTION_CLASS = "phpvms-map-attribution-control";

export type CreateMapOptions = {
  config: MapConfig;
  theme: MapTheme;
  /** Shown via `renderStaticFallback` when WebGL2 is unsupported or the context budget is exhausted. */
  fallback?: FallbackRouteSummary;
  /**
   * 3D terrain from a DEM. Default `true`.
   *
   * Terrain is what makes MapLibre clamp the pitch against the ground instead
   * of letting the camera tilt through it, which is the point of raising
   * `maxPitch` above MapLibre's default 60. It also costs a second tile stream,
   * so a surface that never tilts can pass `false`.
   */
  terrain?: boolean;
};

export type CreateMapHandle = {
  map: MapLibreMapType;
  /** `map.remove()` (frees the WebGL context + every custom layer's own `onRemove`) plus context-budget bookkeeping and the resize observer. */
  destroy(): void;
};

export type CreateMapResult =
  | ({ ok: true } & CreateMapHandle)
  | { ok: false; reason: "no-webgl2" | "context-budget-exceeded" | "gpu-init-failed" };

/**
 * Resolved against this module's own URL, NOT used as-is.
 *
 * In dev, Vite hands back a ROOT-RELATIVE path (`/@fs/...?worker_file&type=module`). The app is
 * served from the Laravel host while Vite serves modules from its own origin, so the browser
 * resolved that against the app host and got its HTML 404 back — "blocked because of a
 * disallowed MIME type (text/html)". `import.meta.url` is this module's real dev-server URL, so
 * resolving against it restores the right origin. In production both are already same-origin and
 * this is a no-op. The alternative — `server.origin` in each Vite config — would hard-code a dev
 * origin and break the Sail/Docker setups that reach the dev server by a different host.
 */
setWorkerUrl(new URL(maplibreWorkerUrl, import.meta.url).href);

const TERRAIN_SOURCE_ID = "phpvms-terrain-dem";

/**
 * Attach the DEM and turn on 3D terrain.
 *
 * Reverses Non-Goal 3 in design.md ("DEM terrain, day/night sky, chase-camera
 * cinematics"), deliberately: without terrain there is nothing for MapLibre to
 * constrain the camera against, so a raised `maxPitch` just tilts the view
 * through the ground. See design.md Risk 4 — the Tier 1 route was never tested
 * at the steep poses this now makes reachable.
 *
 * Owns its OWN try/catch rather than relying on the `load` handler's. The
 * enclosing try/catch would catch a throw from here, but everything after this
 * call in the same block — `applyLayers`, the attribution, the layer control —
 * would be skipped, so a bad DEM would silently cost the map its overlays.
 * Caught by the layer-control test when this was first wired in, not predicted.
 */
function applyTerrain(map: MapLibreMapType, enabled: boolean): void {
  if (!enabled) return;

  try {
    map.addSource(TERRAIN_SOURCE_ID, {
      type: "raster-dem",
      url: MAPTERHORN_TERRAIN_URL,
    });
    map.setTerrain({ source: TERRAIN_SOURCE_ID });
  } catch (error: unknown) {
    // A flat map is a perfectly good map; the overlays matter more.
    console.error("@phpvms/map: 3D terrain unavailable, continuing without it", error);
  }
}

/** Constructing the map is its own function so the v6 throw can be caught around one call. */
function buildMap(el: HTMLElement, style: StyleSpecification): MapLibreMapType {
  return new MapLibreMap({
    container: el,
    style,
    // The package owns its own combined attribution surface — `../acars/.../MapLibreMap.svelte`
    // drops maplibre's own control for the same reason (it collided with their nav rail); left on
    // here it duplicates the credit rather than just looking different, since both would render.
    attributionControl: false,
    // powerPreference deliberately left at its default (design.md D16) — 'high-performance'
    // forces a laptop onto its discrete GPU for what is, at most, a route line.
    //
    // MapLibre's default is 60, which is what stopped the camera tilting further toward the
    // horizon. 85 is MapLibre's own ceiling. With terrain enabled (below) MapLibre additionally
    // constrains the camera against the ground, so this is a ceiling rather than a free-for-all.
    maxPitch: 85,
  });
}

function degradeToFallback(
  el: HTMLElement,
  options: CreateMapOptions,
  reason: "no-webgl2" | "context-budget-exceeded" | "gpu-init-failed",
): CreateMapResult {
  if (options.fallback) renderStaticFallback(el, options.fallback);
  return { ok: false, reason };
}

/** The resize observer for each live instance, keyed the same way `context-budget.ts` keys its map — so a `createMap` call reusing an existing instance also reuses (and later disposes) the same observer, not a leaked second one. */
const resizeObservers = new WeakMap<HTMLElement, ResizeObserver>();
/** The attribution `<div>` `createMap` appended to `el` — tracked so `destroyMap` can remove it, mirroring `resizeObservers`. */
const attributionEls = new WeakMap<HTMLElement, HTMLElement>();

export async function createMap(
  el: HTMLElement,
  options: CreateMapOptions,
): Promise<CreateMapResult> {
  const reused = existingMapFor(el);
  if (reused) {
    // A second (or Nth) owner for an el that already has a live map — bump the refcount so THIS
    // owner's eventual `destroy()` cannot tear the map down out from under the first owner
    // (`context-budget.ts`'s `unregisterMap` doc comment; confirmed a real bug, codex review
    // verified: every reuse used to return an independent closure that called `map.remove()`
    // unconditionally).
    registerMap(el, reused);
    const resizeObserver = resizeObservers.get(el);
    return {
      ok: true,
      map: reused,
      destroy: () =>
        resizeObserver
          ? destroyMapWithObserver(el, reused, resizeObserver)
          : destroyMap(el, reused),
    };
  }

  if (!supportsWebGL2()) return degradeToFallback(el, options, "no-webgl2");
  if (!canCreateNewContext()) return degradeToFallback(el, options, "context-budget-exceeded");

  // Reserved synchronously, BEFORE the `await` below — closes a TOCTOU race (confirmed a real
  // bug, codex review verified): `canCreateNewContext()` was checked, then `resolveStyle(...)`
  // was awaited, then `registerMap` ran only after that. Two concurrent `createMap()` calls for
  // two DIFFERENT elements could both observe room under the budget and both proceed, together
  // exceeding it. `resolveStyle`/`fetchStyle` (`style.ts`) never rejects — it catches internally
  // and falls back to a flat colour — so this reservation is always either promoted by
  // `registerMap` below or intentionally left in place for the caller to release; the one
  // failure path that WOULD leak it — the constructor throwing — releases it explicitly below.
  reserveContextSlot(el);

  const style = await resolveStyle(options.config, options.theme);

  let map: MapLibreMapType;
  try {
    map = buildMap(el, style);
  } catch {
    // maplibre v6.7.0 THROWS `GPUInitializationError` from the constructor when the WebGL2
    // context cannot be created; v5 fired an `error` event no listener could catch and returned
    // a partially built map. `supportsWebGL2()` above screens the common case, but context
    // creation can still fail (GPU reset, driver-level context limits) — which is exactly what
    // the static fallback is for (design.md D16). Without this the promise would reject AND the
    // slot reserved above would leak, permanently shrinking the context budget.
    releaseContextSlot(el);
    return degradeToFallback(el, options, "gpu-init-failed");
  }

  map.setPixelRatio(cappedPixelRatio());

  const attributionEl = document.createElement("div");
  attributionEl.className = ATTRIBUTION_CLASS;
  el.appendChild(attributionEl);
  attributionEls.set(el, attributionEl);

  map.on("load", () => {
    // Defensive on purpose: maplibre's own `Evented.fire()` has no isolation between listeners
    // in the same dispatch (`for (const listener of listeners) listener.call(...)`, no
    // try/catch) — a throw from a persistent `.on('load', ...)` listener (this one) aborts that
    // loop and, critically, the SEPARATE one-time-listener loop that comes after it in the same
    // `fire()` call never runs either. `whenLoaded()` (below) is exactly such a one-time
    // listener (`map.once('load')`), registered by every caller AFTER this one. Without this
    // try/catch, a single malformed overlay layer config (`applyLayers`, server-side data) would
    // hang `whenLoaded()` forever — confirmed a real bug (codex review, verified) by reading
    // maplibre-gl's own `Evented.fire()` source, not assumed.
    try {
      // MapLibre takes projection from the style for a locally-authored style; a remote style URL
      // (the two curated Carto ones, or the operator's custom one) has no such field, so the globe
      // is forced on at runtime instead (design.md D2, confirmed against `Map#setProjection`).
      map.setProjection({ type: "globe" });
      applyTerrain(map, options.terrain ?? true);
      applyLayers(map, options.config.layers);
      renderAttribution(attributionEl, style, options.config.layers);
      // Dynamically imported, and deliberately NOT re-exported from `index.ts`:
      // a static import puts the control's DOM + injected CSS in the EAGER
      // chunk, which every consumer of this package pays for even on a surface
      // with no overlay layers at all. That is a real regression, not a
      // hypothetical — it took the Tier 0 chunk from 3,683 to 5,207 bytes
      // gzipped and tripped `tiering.build.test.ts`'s 5,000-byte budget.
      //
      // Loaded AFTER `applyLayers` because `onAdd` calls `applyLayerVisibility`,
      // which needs the layers to already exist on the style. The `.catch` is
      // required rather than tidy: this runs after the synchronous handler has
      // returned, so the enclosing try/catch cannot see a rejection, and an
      // unhandled one here would be an unhandled rejection rather than a
      // logged, survivable failure.
      if (options.config.layers.length > 0) {
        void import("./layer-control.ts")
          .then(({ LayerControl }) => {
            // The map can be destroyed while this import is in flight — a user
            // navigating away mid-load is enough. `Map#remove()` does `delete
            // this.style` and `Map#getLayer` is `return this.style.getLayer(e)`,
            // so `onAdd`'s `applyLayerVisibility` would throw "Cannot read
            // properties of undefined (reading 'getLayer')" on a torn-down map.
            // Observed in a real browser console, then confirmed against
            // maplibre-gl's own source rather than inferred from the message.
            //
            // `existingMapFor` is the liveness signal: `unregisterMap` clears
            // the entry during teardown, so a mismatch means this map is gone
            // (or the element has been rebound to a newer one, which must not
            // get a control built from the old call's config either).
            if (existingMapFor(el) !== map) return;
            map.addControl(new LayerControl({ layers: options.config.layers }));
          })
          .catch((error: unknown) => {
            console.error("@phpvms/map: error adding the layer control", error);
          });
      }
    } catch (error) {
      console.error("@phpvms/map: error applying overlay layers/attribution on load", error);
    }
  });

  // `trackResize` (maplibre's own default) only watches the WINDOW; a container that resizes from
  // its own layout (a sidebar toggling, a flex/grid change) without the window resizing needs this.
  const resizeObserver = new ResizeObserver(() => map.resize());
  resizeObserver.observe(el);
  resizeObservers.set(el, resizeObserver);

  registerMap(el, map); // promotes the `reserveContextSlot` reservation above to a real registration

  return { ok: true, map, destroy: () => destroyMapWithObserver(el, map, resizeObserver) };
}

function destroyMapWithObserver(
  el: HTMLElement,
  map: MapLibreMapType,
  resizeObserver: ResizeObserver,
): void {
  if (!unregisterMap(el)) return; // another owner still holds this map/observer — do not tear it down under them
  resizeObserver.disconnect();
  resizeObservers.delete(el);
  teardownMap(el, map);
}

function destroyMap(el: HTMLElement, map: MapLibreMapType): void {
  if (!unregisterMap(el)) return;
  teardownMap(el, map);
}

/** The actual, one-time teardown — only ever reached once `unregisterMap` confirms this is the LAST owner. */
function teardownMap(el: HTMLElement, map: MapLibreMapType): void {
  map.remove();
  const attributionEl = attributionEls.get(el);
  if (attributionEl) {
    attributionEl.remove();
    attributionEls.delete(el);
  }
}

/**
 * Resolves once `map`'s style has finished loading — `createMap` itself resolves as soon as the
 * `Map` is constructed, NOT once its style is ready (the `load` handler above runs later, async).
 * Any caller that adds a layer/source to the returned map (both adapters do, via `createRoute`,
 * `createLiveFlightMarkers`, `createWaypointMarkers` — all of which self-register) must await
 * this first, or maplibre throws "Style is not done loading".
 */
export async function whenLoaded(map: MapLibreMapType): Promise<void> {
  if (map.loaded()) return;
  await map.once("load");
}

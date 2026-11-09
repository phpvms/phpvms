import type { Map as MapLibreMapType } from "maplibre-gl";

/**
 * Cross-instance WebGL context accounting (design.md D16, tasks.md 2b.5's
 * remaining half — the disposal half already lives in `anchored-scene.ts`'s
 * `onRemove`). Each `maplibregl.Map` owns its own canvas and context, which
 * `createMap` (`base-map.ts`) constructs — this module is what lets it
 * REUSE an instance already alive for the same element, and CAP how many new
 * ones it will create, rather than letting a page mount an unbounded number.
 *
 * Browsers cap live WebGL contexts (commonly ~8-16, unspecified and
 * implementation-dependent); `MAX_CONTEXTS` is set conservatively below the
 * low end of that range so a page hits the package's own cap — with a
 * caller-visible fallback outcome — before it hits the browser's own hard
 * limit, which fails ungracefully (silently drops the OLDEST context,
 * "WebGL: CONTEXT_LOST_WEBGL").
 */

const MAX_CONTEXTS = 6;

const activeMaps = new Map<HTMLElement, MapLibreMapType>();
/**
 * How many owners currently hold `el`'s map — `createMap`'s reuse path (`existingMapFor`) hands
 * the SAME `MapLibreMapType` to a second caller rather than constructing a new one, but each
 * caller still gets its own independent `destroy()` closure. Without a refcount, EITHER owner's
 * `destroy()` tears down the map unconditionally (`map.remove()`), killing it out from under the
 * other — confirmed a real bug (codex review, verified). `unregisterMap` only reports "safe to
 * actually tear down" once the LAST owner has released it.
 */
const refCounts = new Map<HTMLElement, number>();
/**
 * Elements with a context construction IN PROGRESS (reserved via `reserveContextSlot`, before
 * `registerMap` promotes the reservation to a real entry). Exists to close a TOCTOU race
 * (codex review, verified): `canCreateNewContext()` used to be checked, then `await
 * resolveStyle(...)`, then `registerMap` — two concurrent `createMap()` calls for two DIFFERENT
 * elements could both observe room under `MAX_CONTEXTS` and both proceed, together exceeding it.
 * Counting pending reservations in `canCreateNewContext` closes that window.
 */
const pending = new Set<HTMLElement>();

/** An existing live map for `el`, if `createMap` was already called for it and never torn down. */
export function existingMapFor(el: HTMLElement): MapLibreMapType | undefined {
  return activeMaps.get(el);
}

/**
 * Reserve a budget slot for `el` synchronously, before any `await` — see `pending`'s doc comment.
 * Every reservation must be resolved by either `registerMap` (construction succeeded) or
 * `releaseContextSlot` (it did not), or the slot leaks against the budget forever.
 */
export function reserveContextSlot(el: HTMLElement): void {
  pending.add(el);
}

/** Release a reservation that never became a real registration (e.g. the caller gave up). */
export function releaseContextSlot(el: HTMLElement): void {
  pending.delete(el);
}

/** Register a new owner for `el`'s map — the first owner promotes any pending reservation; a later one (the reuse path) bumps the refcount instead of overwriting it. */
export function registerMap(el: HTMLElement, map: MapLibreMapType): void {
  pending.delete(el);
  activeMaps.set(el, map);
  refCounts.set(el, (refCounts.get(el) ?? 0) + 1);
}

/**
 * Release one owner's claim on `el`'s map. Returns `true` only when this was the LAST owner —
 * only then is it safe for the caller to actually tear the map down (`map.remove()`, etc.); a
 * `false` return means another owner still holds it.
 */
export function unregisterMap(el: HTMLElement): boolean {
  const remaining = (refCounts.get(el) ?? 1) - 1;
  if (remaining > 0) {
    refCounts.set(el, remaining);
    return false;
  }
  refCounts.delete(el);
  activeMaps.delete(el);
  return true;
}

/** Whether a NEW context can be created without exceeding the budget. Reusing an existing one never counts against this. */
export function canCreateNewContext(): boolean {
  return activeMaps.size + pending.size < MAX_CONTEXTS;
}

export function activeContextCount(): number {
  return activeMaps.size;
}

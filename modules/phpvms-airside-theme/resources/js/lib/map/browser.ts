/**
 * Browser lifecycle helpers (design.md D16, tasks.md 2b.4a/2b.6).
 *
 * `createMap` (phase 3, `base-map.ts`) is where these actually get wired
 * into a real map construction — that module does not exist yet. These are
 * the pure, callable-now pieces phase 3 will use, kept here so they exist as
 * package deliverables in their own right rather than waiting on `createMap`.
 */

const MAX_PIXEL_RATIO = 2;

/**
 * Render at `Math.min(devicePixelRatio, 2)`. Phones ship DPR 3 and above,
 * and rendering at native ratio multiplies fragment cost for detail nobody
 * can resolve — the single cheapest mobile win, easy to forget because it is
 * invisible on a 1x or 2x desktop display.
 */
export function cappedPixelRatio(
  reportedRatio: number = typeof window !== "undefined" ? window.devicePixelRatio : 1,
): number {
  if (!Number.isFinite(reportedRatio) || reportedRatio <= 0) return 1;
  return Math.min(reportedRatio, MAX_PIXEL_RATIO);
}

/**
 * Whether this browser can provide the WebGL2 context maplibre-gl v5
 * requires. An unsupported device gets no map at all, not a degraded one
 * (design.md D16) — callers use this to decide whether to attempt
 * `createMap` or go straight to `renderStaticFallback`.
 *
 * A throwaway canvas, never attached to the DOM: `getContext('webgl2')` is
 * the only reliable capability probe, and creating the context has no
 * observable side effect if the canvas is discarded immediately after.
 */
export function supportsWebGL2(): boolean {
  if (typeof document === "undefined") return false;
  try {
    const canvas = document.createElement("canvas");
    const gl = canvas.getContext("webgl2");
    return gl !== null;
  } catch {
    return false;
  }
}

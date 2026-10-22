import type { ScenePosition } from "./types.ts";

/**
 * Chunk level-of-detail (design.md D14). Per-chunk anchoring buys precision
 * that is invisible when the camera is framed to a whole route — at
 * `CHUNK_MAX_LENGTH_M` a 15,000 km long-haul is ~750 chunks, and drawing every
 * one every frame when the camera cannot resolve better than a handful of
 * pixels is arithmetic from the constant, not a measurement. Framed to the
 * whole route, chunks are merged into a small number of coarser anchor
 * groups; zoomed in, the full per-20km chunking is used and chunks outside
 * the visible bounds are skipped.
 *
 * The switch threshold is a `map.getZoom()` value, measured empirically (see
 * the harness report) rather than guessed.
 */

/**
 * Merge every `groupSize` consecutive chunks into one, keeping the first
 * chunk's anchor. Chunks already share their joining point (design.md D2's
 * `chunkLine`), so a merge drops the duplicate boundary point at each seam
 * rather than concatenating chunks verbatim.
 */
export function mergeChunks<T extends ScenePosition>(chunks: T[][], groupSize: number): T[][] {
  if (groupSize <= 1) return chunks;

  const merged: T[][] = [];
  for (let i = 0; i < chunks.length; i += groupSize) {
    const group = chunks.slice(i, i + groupSize);
    const points = group.flatMap((chunk, index) => (index === 0 ? chunk : chunk.slice(1)));
    merged.push(points);
  }
  return merged;
}

export type LonLatBounds = { west: number; south: number; east: number; north: number };

/**
 * Whether an anchor falls inside `bounds`, expanded by `marginDeg` on every
 * side. The margin exists because a chunk's ANCHOR can sit just outside the
 * visible viewport while part of the chunk's own ≤20 km extent is still on
 * screen; the margin is generous rather than exact because a false positive
 * (drawing one extra off-screen chunk) is cheap and a false negative (a
 * visible chunk not drawn) is a visible gap.
 *
 * Does not attempt antimeridian-aware bounds — `map.getBounds()` already
 * returns a wrapped, monotonic west/east pair for a view that straddles
 * ±180°, so a real caller's bounds do not exhibit the wraparound
 * `chunking.ts`'s `groundMetresBetween` has to correct for.
 */
export function anchorInBounds(
  anchor: ScenePosition,
  bounds: LonLatBounds,
  marginDeg: number,
): boolean {
  return (
    anchor.lon >= bounds.west - marginDeg &&
    anchor.lon <= bounds.east + marginDeg &&
    anchor.lat >= bounds.south - marginDeg &&
    anchor.lat <= bounds.north + marginDeg
  );
}

export type LodSelection<T> = { chunks: T[][]; merged: boolean };

/**
 * Pick which chunks to hand the renderer this frame: merged and unculled
 * below `thresholdZoom` (the whole route is in view, so full chunking buys
 * nothing), full-resolution and frustum-culled to `bounds` at or above it.
 */
export function selectChunksForLod<T extends ScenePosition>(
  fineChunks: T[][],
  zoom: number,
  thresholdZoom: number,
  bounds: LonLatBounds,
  options: { mergeGroupSize?: number; marginDeg?: number } = {},
): LodSelection<T> {
  if (zoom < thresholdZoom) {
    return { chunks: mergeChunks(fineChunks, options.mergeGroupSize ?? 8), merged: true };
  }
  const marginDeg = options.marginDeg ?? 5;
  return {
    chunks: fineChunks.filter((chunk) => anchorInBounds(chunk[0], bounds, marginDeg)),
    merged: false,
  };
}

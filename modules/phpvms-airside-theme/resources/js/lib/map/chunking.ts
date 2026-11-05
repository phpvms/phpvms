/**
 * Anchored chunking (design.md D2). `transform.getMatrixForModel` is a
 * per-location matrix, not a world coordinate system, so one tangent plane
 * only stays accurate for tens of kilometres. Routes split into runs of at
 * most `CHUNK_MAX_LENGTH_M`, each drawn relative to its own anchor.
 *
 * Ported and generalised from `../acars/.../altitude-scene.ts`'s
 * `chunkLine`/`groundMetresBetween` (kept during the gated spike at
 * `spike/route-spike.ts`; promoted here with the antimeridian fix task 2.1
 * asked for — the original flat-earth formula was never exercised near ±180°
 * longitude).
 */

import type { ScenePosition } from "./types.ts";

export type { ScenePosition };

/** Ground distance a single tangent plane stays accurate over (~8 m of drop at the edge). */
export const CHUNK_MAX_LENGTH_M = 20_000;

const METRES_PER_DEGREE_LAT = 110_540;
const METRES_PER_DEGREE_LON = 111_320;

/** Longitude delta normalised to (-180, 180], so a crossing of the antimeridian reads as a small step. */
function normalizedLonDeltaDeg(fromLon: number, toLon: number): number {
  return ((((toLon - fromLon + 180) % 360) + 360) % 360) - 180;
}

/**
 * Flat-earth ground distance. Only ever used to decide where a chunk ENDS,
 * never to place one — `getMatrixForModel` does the real placement.
 *
 * Longitude is normalised across the antimeridian first: an unnormalised
 * `b.lon - a.lon` reads a 179.9°→-179.9° step (0.2° apart) as ~359.8°, which
 * would end every chunk one point early for the rest of a route that crosses
 * ±180° — geometrically safe (shorter chunks, not wrong placement) but not
 * what the 20 km budget intends.
 */
export function groundMetresBetween(a: ScenePosition, b: ScenePosition): number {
  const east =
    normalizedLonDeltaDeg(a.lon, b.lon) * METRES_PER_DEGREE_LON * Math.cos((a.lat * Math.PI) / 180);
  const north = (b.lat - a.lat) * METRES_PER_DEGREE_LAT;
  return Math.hypot(east, north);
}

/**
 * Split a line into runs no longer than `maxLengthM` on the ground.
 *
 * Consecutive chunks SHARE their joining point, so two chunks meet without a
 * gap. A line shorter than the budget comes back as a single chunk; 0 or 1
 * points come back as nothing at all — there is no line to draw from one
 * position.
 */
export function chunkLine<T extends ScenePosition>(
  points: T[],
  maxLengthM: number = CHUNK_MAX_LENGTH_M,
): T[][] {
  if (points.length < 2) return [];

  const chunks: T[][] = [];
  let current: T[] = [points[0]];
  let length = 0;

  for (const point of points.slice(1)) {
    const legLength = groundMetresBetween(current[current.length - 1], point);
    // Close BEFORE adding this point if doing so would push the chunk over budget — checking
    // AFTER appending (as this used to) let one closing leg's length count toward the chunk it
    // was closing rather than the next one, so two legs that together exceed the budget stayed
    // in a single oversized chunk (confirmed a real bug, codex review verified: see
    // chunking.test.ts's regression case). `current.length > 1` guards a single leg that is
    // ALREADY longer than the budget by itself — there is no earlier point to split at, so it
    // unavoidably becomes one oversized chunk (chunkLine only splits at existing points, it
    // never interpolates new ones).
    if (length + legLength > maxLengthM && current.length > 1) {
      chunks.push(current);
      current = [current[current.length - 1]]; // new chunk starts from the shared joining point
      length = 0;
    }
    length += legLength;
    current.push(point);
  }

  if (current.length > 1) chunks.push(current);
  return chunks;
}

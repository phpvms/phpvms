import { describe, expect, it } from "vitest";
import { CHUNK_MAX_LENGTH_M, chunkLine, groundMetresBetween } from "./chunking.ts";

const p = (lon: number, lat: number, altitude = 0) => ({ lon, lat, altitude });

describe("groundMetresBetween", () => {
  it("is zero for identical points", () => {
    expect(groundMetresBetween(p(-73.7781, 40.6413), p(-73.7781, 40.6413))).toBe(0);
  });

  it("reads a small step across the antimeridian as small, not ~360°'s worth", () => {
    // 179.9° -> -179.9° is a 0.2° step (~22 km at the equator), not 359.8°'s worth (~40,000 km).
    const near = groundMetresBetween(p(179.9, 0), p(-179.9, 0));
    expect(near).toBeLessThan(30_000);
  });

  it("agrees on distance regardless of which side of the antimeridian is 'a'", () => {
    const forward = groundMetresBetween(p(179.9, 0), p(-179.9, 0));
    const backward = groundMetresBetween(p(-179.9, 0), p(179.9, 0));
    expect(forward).toBeCloseTo(backward, 0);
  });
});

describe("chunkLine", () => {
  it("returns nothing for 0 points", () => {
    expect(chunkLine([])).toEqual([]);
  });

  it("returns nothing for 1 point — there is no line to draw", () => {
    expect(chunkLine([p(0, 0)])).toEqual([]);
  });

  it("returns a single chunk for 2 points under the budget", () => {
    const points = [p(0, 0), p(0.01, 0)];
    const chunks = chunkLine(points);
    expect(chunks).toEqual([points]);
  });

  it("returns a single chunk for a line entirely under the budget", () => {
    // ~5 km total, well under the 20 km budget.
    const points = [p(0, 0), p(0.02, 0), p(0.04, 0)];
    const chunks = chunkLine(points);
    expect(chunks).toHaveLength(1);
    expect(chunks[0]).toHaveLength(3);
  });

  it("closes a chunk BEFORE a leg would push it over budget, not after — the budget is a ceiling, not a trigger", () => {
    // Two ~11,132 m legs with a 15,000 m budget: a->b alone (11,132 m) is under budget, but
    // a->b->c (22,264 m) is not. The fix (codex review, verified real): the old code appended
    // c to the SAME chunk as a/b and only checked the budget after, producing one ~22 km chunk
    // for a 15 km budget — silently violating design.md D2's tangent-plane accuracy guarantee.
    // The correct behaviour closes at b (the last point that keeps the chunk under budget) and
    // starts a NEW chunk from b, sharing the joining point same as any other split.
    const a = p(0, 0);
    const b = p(0.1, 0); // ~11,132 m east at the equator
    const c = p(0.2, 0); // another ~11,132 m
    const chunks = chunkLine([a, b, c], 15_000);
    expect(chunks).toEqual([
      [a, b],
      [b, c],
    ]);
    for (const chunk of chunks) {
      expect(groundMetresBetween(chunk[0], chunk.at(-1)!)).toBeLessThanOrEqual(15_000);
    }
  });

  it("cannot split a single leg longer than the budget — there is no point to split at", () => {
    // A leg longer than the whole budget by itself (e.g. server-side track simplification,
    // design.md D15, makes sparse input the normal case) unavoidably becomes one oversized
    // chunk: `chunkLine` only splits at EXISTING points, it does not interpolate new ones.
    const a = p(0, 0);
    const b = p(1, 0); // ~111,320 m — one leg, far over a 15,000 m budget
    const chunks = chunkLine([a, b], 15_000);
    expect(chunks).toEqual([[a, b]]);
  });

  it("splits a route long enough to need multiple chunks, sharing joining points", () => {
    // 21 points, 0.05° apart in longitude (~5,566 m each at the equator) -> ~111 km total,
    // well past CHUNK_MAX_LENGTH_M, so this must produce more than one chunk.
    const points = Array.from({ length: 21 }, (_, i) => p(i * 0.05, 0));
    const chunks = chunkLine(points, CHUNK_MAX_LENGTH_M);
    expect(chunks.length).toBeGreaterThan(1);
    for (let i = 1; i < chunks.length; i++) {
      expect(chunks[i][0]).toBe(chunks[i - 1].at(-1));
    }
    // No point is dropped: chunk lengths minus the (n-1) shared joins reconstruct the input.
    const totalUnique = chunks.reduce((sum, c) => sum + c.length, 0) - (chunks.length - 1);
    expect(totalUnique).toBe(points.length);
  });

  it("chunks a route crossing the antimeridian without collapsing to one point per chunk", () => {
    const points = [p(179.95, 10), p(179.99, 10), p(-179.99, 10), p(-179.95, 10)];
    const chunks = chunkLine(points);
    // The whole route is well under 20 km once the antimeridian wrap is handled correctly,
    // so it should come back as one chunk, not four degenerate ones.
    expect(chunks).toEqual([points]);
  });
});

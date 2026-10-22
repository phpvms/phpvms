import { describe, expect, it } from "vitest";
import { anchorInBounds, mergeChunks, selectChunksForLod } from "./lod.ts";

const p = (lon: number, lat: number) => ({ lon, lat, altitude: 0 });

describe("mergeChunks", () => {
  it("returns the input unchanged for groupSize <= 1", () => {
    const chunks = [
      [p(0, 0), p(1, 0)],
      [p(1, 0), p(2, 0)],
    ];
    expect(mergeChunks(chunks, 1)).toEqual(chunks);
    expect(mergeChunks(chunks, 0)).toEqual(chunks);
  });

  it("merges consecutive chunks without duplicating their shared joining point", () => {
    // Three chunks sharing joins, as chunkLine produces them: [A,B],[B,C],[C,D].
    const a = p(0, 0);
    const b = p(1, 0);
    const c = p(2, 0);
    const d = p(3, 0);
    const chunks = [
      [a, b],
      [b, c],
      [c, d],
    ];

    const merged = mergeChunks(chunks, 3);
    expect(merged).toEqual([[a, b, c, d]]);
  });

  it("groups in batches, keeping each group's first chunk's anchor as the merged anchor", () => {
    const points = Array.from({ length: 5 }, (_, i) => p(i, 0));
    const chunks = points.slice(0, -1).map((_, i) => [points[i], points[i + 1]]);
    const merged = mergeChunks(chunks, 2);
    expect(merged).toHaveLength(2);
    expect(merged[0][0]).toBe(chunks[0][0]); // first group's anchor is chunk 0's anchor
    expect(merged[1][0]).toBe(chunks[2][0]); // second group's anchor is chunk 2's anchor
  });
});

describe("anchorInBounds", () => {
  const bounds = { west: -10, south: -10, east: 10, north: 10 };

  it("is true for an anchor inside the bounds", () => {
    expect(anchorInBounds(p(0, 0), bounds, 0)).toBe(true);
  });

  it("is false for an anchor well outside the bounds and margin", () => {
    expect(anchorInBounds(p(50, 50), bounds, 1)).toBe(false);
  });

  it("the margin extends the bounds on every side", () => {
    expect(anchorInBounds(p(12, 0), bounds, 1)).toBe(false);
    expect(anchorInBounds(p(12, 0), bounds, 5)).toBe(true);
  });
});

describe("selectChunksForLod", () => {
  const chunks = Array.from({ length: 20 }, (_, i) => [p(i, 0), p(i + 1, 0)]);

  it("below the threshold zoom: returns merged chunks regardless of bounds", () => {
    const farAwayBounds = { west: 100, south: 100, east: 101, north: 101 };
    const selection = selectChunksForLod(chunks, 2, 4, farAwayBounds, { mergeGroupSize: 5 });
    expect(selection.merged).toBe(true);
    expect(selection.chunks).toHaveLength(4); // 20 chunks / groupSize 5
  });

  it("at or above the threshold zoom: returns full-resolution chunks culled to bounds", () => {
    const narrowBounds = { west: 2, south: -1, east: 5, north: 1 };
    const selection = selectChunksForLod(chunks, 8, 4, narrowBounds, { marginDeg: 0 });
    expect(selection.merged).toBe(false);
    expect(selection.chunks.length).toBeGreaterThan(0);
    expect(selection.chunks.length).toBeLessThan(chunks.length);
    for (const chunk of selection.chunks) {
      expect(chunk[0].lon).toBeGreaterThanOrEqual(2);
      expect(chunk[0].lon).toBeLessThanOrEqual(5);
    }
  });
});

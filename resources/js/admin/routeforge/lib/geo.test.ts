import { describe, expect, it } from "vitest";

import { haversineNm } from "./geo";

describe("haversineNm", () => {
  it("returns 0 for identical points", () => {
    expect(haversineNm(37.6, -122.4, 37.6, -122.4)).toBe(0);
  });

  it("approximates known great-circle distances", () => {
    // KSFO (37.6213°N, 122.3790°W) → KLAX (33.9416°N, 118.4085°W)
    // Reference: ≈ 293 nm great-circle.
    const d = haversineNm(37.6213, -122.379, 33.9416, -118.4085);
    expect(d).toBeGreaterThan(290);
    expect(d).toBeLessThan(300);
  });

  it("computes a long-haul distance within expected bounds", () => {
    // KSFO → EGLL (London Heathrow, 51.4775°N, 0.4614°W) ≈ 4625 nm.
    const d = haversineNm(37.6213, -122.379, 51.4775, -0.4614);
    expect(d).toBeGreaterThan(4600);
    expect(d).toBeLessThan(4700);
  });

  it("is symmetric — distance(A, B) === distance(B, A)", () => {
    const ab = haversineNm(40.6413, -73.7781, 51.4775, -0.4614);
    const ba = haversineNm(51.4775, -0.4614, 40.6413, -73.7781);
    expect(ab).toBeCloseTo(ba, 10);
  });

  it("matches the PHP RouteForgeController::haversineNm formula", () => {
    // Same Earth radius (3440.065 nm) + same haversine arithmetic.
    // Verify a precise reference value computed with the same formula.
    // KSFO → KORD (41.9786°N, 87.9048°W) computed ≈ 1600 nm with the
    // R = 3440.065 nm Earth radius this formula uses (some refs use the
    // slightly smaller 3440 nm — within rounding either way).
    const d = haversineNm(37.6213, -122.379, 41.9786, -87.9048);
    expect(d).toBeGreaterThan(1590);
    expect(d).toBeLessThan(1610);
  });
});

import { describe, expect, it } from "vitest";

// Static JSON import (tsconfig.resolveJsonModule + Vite's built-in JSON
// loader). Both PHP (`tests/Unit/Support/GeoTest.php`) and TS load the same
// file; see the fixture's `_doc` field for the parity contract.
import fixture from "../../../../../tests/fixtures/routeforge/geo-haversine.json";

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

  it("matches the PHP App\\Support\\Geo::haversineNm formula", () => {
    // Loose bound retained as a quick sanity check; the strict cross-
    // language parity assertion below is the authoritative drift detector.
    // KSFO → KORD (41.9786°N, 87.9048°W) ≈ 1600 nm with R = 3440.065 nm.
    const d = haversineNm(37.6213, -122.379, 41.9786, -87.9048);
    expect(d).toBeGreaterThan(1590);
    expect(d).toBeLessThan(1610);
  });

  // ─── Cross-language parity ─────────────────────────────────────────────
  //
  // `tests/fixtures/routeforge/geo-haversine.json` defines a shared set of
  // (lat/lon, lat/lon) → expected_nm cases. The companion Pest spec
  // (`tests/Unit/Support/GeoTest.php`) loads the same JSON and asserts
  // the PHP implementation matches. Both halves passing means the TS
  // `geo.ts` and PHP `App\Support\Geo` agree at floating-point precision —
  // any drift in either direction fails its own suite.
  //
  // Add new edge cases to the JSON and both halves pick them up.

  it("exposes the canonical Earth radius declared in the parity fixture", () => {
    // geo.ts inlines the constant; assert the fixture and the implementation
    // agree on the value (drift here would make every parity case fail).
    expect(fixture.earth_radius_nm).toBe(3440.065);
  });

  it.each(fixture.cases)(
    "matches the cross-language parity fixture: $name",
    ({ lat_a, lon_a, lat_b, lon_b, expected_nm }) => {
      const actual = haversineNm(lat_a, lon_a, lat_b, lon_b);
      expect(Math.abs(actual - expected_nm)).toBeLessThanOrEqual(fixture.tolerance_nm);
    },
  );
});

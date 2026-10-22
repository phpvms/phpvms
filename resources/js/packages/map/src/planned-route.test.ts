import { describe, expect, it } from "vitest";
import { resolvePlannedRoute } from "./planned-route.ts";

describe("resolvePlannedRoute", () => {
  it("converts a fix's own altitude from feet to metres", () => {
    const [point] = resolvePlannedRoute(
      [{ ident: "ABC", lat: 1, lon: 2, altitudeFt: 35_000, viaAirway: null }],
      null,
    );
    expect(point.altitude).toBeCloseTo(35_000 * 0.3048);
  });

  it("falls back to the route's fallback altitude when the fix has no altitude of its own", () => {
    // 33,000 is FEET — `MapPlannedRouteData.fallbackAltitudeFt`, derived server-side from
    // `altitude_msl` telemetry, not a flight level.
    const [point] = resolvePlannedRoute(
      [{ ident: "ABC", lat: 1, lon: 2, altitudeFt: null, viaAirway: null }],
      33_000,
    );
    expect(point.altitude).toBeCloseTo(33_000 * 0.3048);
  });

  it("treats the fallback as feet, NOT a flight level — no 100x multiply", () => {
    // Discriminating regression case for the D11 retarget. This parameter used to be
    // `cruiseFlightLevel` and multiplied by 100; that was wrong because `$pirep->level` holds
    // feet in 32 of 33 real rows. If the old FL→ft conversion came back, 35,000 ft would
    // render at 3,500,000 ft — well outside the atmosphere.
    const [point] = resolvePlannedRoute(
      [{ ident: "ABC", lat: 1, lon: 2, altitudeFt: null, viaAirway: null }],
      35_000,
    );
    expect(point.altitude).toBeCloseTo(35_000 * 0.3048);
    expect(point.altitude).not.toBeCloseTo(35_000 * 100 * 0.3048);
  });

  it("falls back to ground level when neither the fix nor the route has an altitude", () => {
    const [point] = resolvePlannedRoute(
      [{ ident: "ABC", lat: 1, lon: 2, altitudeFt: null, viaAirway: null }],
      null,
    );
    expect(point.altitude).toBe(0);
  });

  it("preserves lat/lon exactly and drops ident/viaAirway (a RoutePoint has no use for them)", () => {
    const [point] = resolvePlannedRoute(
      [{ ident: "XYZ", lat: 12.5, lon: -45.25, altitudeFt: 10_000, viaAirway: "A1" }],
      null,
    );
    expect(point.lat).toBe(12.5);
    expect(point.lon).toBe(-45.25);
    expect(point).not.toHaveProperty("ident");
  });
});

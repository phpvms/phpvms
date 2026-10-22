import { describe, expect, it } from "vitest";
import * as THREE from "three";
import { MercatorCoordinate } from "maplibre-gl";
import { localPositionFn, modelMatrixFor, readProjectionFrame } from "./anchored-scene.ts";

describe("readProjectionFrame", () => {
  const argsWithTransition = (transition: number | undefined) => ({
    defaultProjectionData: {
      mainMatrix: new THREE.Matrix4().identity().elements as unknown as number[],
      projectionTransition: transition,
    },
  });

  it("pure mercator (transition 0 or absent): not mid-blend, not globe", () => {
    for (const transition of [0, undefined]) {
      const frame = readProjectionFrame(argsWithTransition(transition), undefined);
      expect(frame.midBlend).toBe(false);
      expect(frame.globeNow).toBe(false);
    }
  });

  it("pure globe (transition 1): not mid-blend, is globe", () => {
    const frame = readProjectionFrame(argsWithTransition(1), undefined);
    expect(frame.midBlend).toBe(false);
    expect(frame.globeNow).toBe(true);
  });

  it("mid-transition (0 < transition < 1): mid-blend AND already counted as globe", () => {
    const frame = readProjectionFrame(argsWithTransition(0.5), undefined);
    expect(frame.midBlend).toBe(true);
    expect(frame.globeNow).toBe(true);
  });

  it("flags a mirroring flip only when the baked space actually changed", () => {
    const stillMercator = readProjectionFrame(argsWithTransition(0), false);
    expect(stillMercator.mirroringFlipped).toBe(false);

    const flippedToGlobe = readProjectionFrame(argsWithTransition(1), false);
    expect(flippedToGlobe.mirroringFlipped).toBe(true);

    const flippedToMercator = readProjectionFrame(argsWithTransition(0), true);
    expect(flippedToMercator.mirroringFlipped).toBe(true);

    // No previous bake yet (first frame) — nothing to have flipped from.
    const firstFrame = readProjectionFrame(argsWithTransition(1), undefined);
    expect(firstFrame.mirroringFlipped).toBe(false);
  });
});

describe("modelMatrixFor / localPositionFn", () => {
  /**
   * These used to run against a stubbed `transform.getMatrixForModel`, which
   * only ever proved the code echoed its own stub. maplibre v6 removed that
   * internal and the matrices are now built in-package, so these assert the
   * real geometry instead — a wrong axis or a wrong scale is a wrong number
   * here, not a plausible-looking coincidence.
   */
  const EARTH_RADIUS_M = 6371008.8;

  it("puts a globe point on the unit sphere, lifted by its altitude", () => {
    const onSurface = new THREE.Vector3().setFromMatrixPosition(
      modelMatrixFor({ lon: 30, lat: -12, altitude: 0 }, true),
    );
    expect(onSurface.length()).toBeCloseTo(1, 6);

    const aloft = new THREE.Vector3().setFromMatrixPosition(
      modelMatrixFor({ lon: 30, lat: -12, altitude: 10_000 }, true),
    );
    expect(aloft.length()).toBeCloseTo(1 + 10_000 / EARTH_RADIUS_M, 6);
  });

  it("puts a mercator point at MapLibre's own mercator coordinate", () => {
    const position = { lon: 12, lat: 34, altitude: 500 };
    const expected = MercatorCoordinate.fromLngLat([position.lon, position.lat], position.altitude);
    const actual = new THREE.Vector3().setFromMatrixPosition(modelMatrixFor(position, false));

    expect(actual.x).toBeCloseTo(expected.x, 9);
    expect(actual.y).toBeCloseTo(expected.y, 9);
    expect(actual.z).toBeCloseTo(expected.z, 9);
  });

  it.each([
    ["globe", true],
    ["mercator", false],
  ])("the anchor decomposes to the origin relative to itself (%s)", (_name, isGlobe) => {
    const anchor = { lon: 10, lat: 20, altitude: 1000 };
    const at = localPositionFn(anchor, isGlobe as boolean)(anchor);

    expect(at.x).toBeCloseTo(0);
    expect(at.y).toBeCloseTo(0);
    expect(at.z).toBeCloseTo(0);
  });

  /**
   * The whole point of the anchored-chunk substrate: local coordinates are
   * METRES from the anchor. 0.5 deg of longitude at 20 deg N is about 52.2 km,
   * so a scale error (unit sphere units, mercator units, feet) misses by
   * orders of magnitude rather than by a rounding step.
   */
  it.each([
    ["globe", true],
    ["mercator", false],
  ])("decomposes a nearby point into metres from the anchor (%s)", (_name, isGlobe) => {
    const anchor = { lon: 10, lat: 20, altitude: 0 };
    const point = { lon: 10.5, lat: 20, altitude: 0 };
    const at = localPositionFn(anchor, isGlobe as boolean)(point);

    const expectedMetres = 0.5 * 111_320 * Math.cos((20 * Math.PI) / 180);
    expect(at.length()).toBeGreaterThan(expectedMetres * 0.98);
    expect(at.length()).toBeLessThan(expectedMetres * 1.02);
  });

  it("carries altitude into the local frame as metres", () => {
    const anchor = { lon: 10, lat: 20, altitude: 0 };
    const aloft = { lon: 10, lat: 20, altitude: 10_000 };
    const at = localPositionFn(anchor, true)(aloft);

    expect(at.length()).toBeGreaterThan(9_800);
    expect(at.length()).toBeLessThan(10_200);
  });
});

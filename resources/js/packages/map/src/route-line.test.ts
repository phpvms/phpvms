import { beforeEach, describe, expect, it, vi } from "vitest";
import * as THREE from "three";
import {
  buildChunkGeometry,
  colorForPoint,
  DEFAULT_ROUTE_COLOR,
  selectionKey,
} from "./route-line.ts";
import type { RoutePoint } from "./types.ts";

beforeEach(() => {
  vi.resetModules();
});

const PHASE_COLORS = { climb: "#f59e0b", cruise: "#22c55e", descent: "#38bdf8" };

describe("colorForPoint", () => {
  it("single-phase track: every point maps to that phase's colour", () => {
    const points: RoutePoint[] = [
      { lat: 0, lon: 0, altitude: 0, phase: "cruise" },
      { lat: 1, lon: 1, altitude: 100, phase: "cruise" },
    ];
    for (const point of points) {
      expect(colorForPoint(point, PHASE_COLORS, DEFAULT_ROUTE_COLOR)).toBe("#22c55e");
    }
  });

  it("all-phases track: each point maps to its own phase's colour", () => {
    expect(
      colorForPoint(
        { lat: 0, lon: 0, altitude: 0, phase: "climb" },
        PHASE_COLORS,
        DEFAULT_ROUTE_COLOR,
      ),
    ).toBe("#f59e0b");
    expect(
      colorForPoint(
        { lat: 0, lon: 0, altitude: 10000, phase: "cruise" },
        PHASE_COLORS,
        DEFAULT_ROUTE_COLOR,
      ),
    ).toBe("#22c55e");
    expect(
      colorForPoint(
        { lat: 0, lon: 0, altitude: 0, phase: "descent" },
        PHASE_COLORS,
        DEFAULT_ROUTE_COLOR,
      ),
    ).toBe("#38bdf8");
  });

  it("unknown phase: falls back to the default colour rather than failing", () => {
    expect(
      colorForPoint(
        { lat: 0, lon: 0, altitude: 0, phase: "PSD" },
        PHASE_COLORS,
        DEFAULT_ROUTE_COLOR,
      ),
    ).toBe(DEFAULT_ROUTE_COLOR);
  });

  it("missing phase: falls back to the default colour", () => {
    expect(colorForPoint({ lat: 0, lon: 0, altitude: 0 }, PHASE_COLORS, DEFAULT_ROUTE_COLOR)).toBe(
      DEFAULT_ROUTE_COLOR,
    );
  });
});

describe("buildChunkGeometry", () => {
  it("one geometry carries the whole chunk's phase colours, switching per vertex", () => {
    const anchor: RoutePoint = { lon: 0, lat: 0, altitude: 0, phase: "climb" };
    const points: RoutePoint[] = [
      anchor,
      { lon: 0.001, lat: 0, altitude: 500, phase: "climb" },
      { lon: 0.002, lat: 0, altitude: 1000, phase: "cruise" },
    ];
    const geometry = buildChunkGeometry(anchor, points, PHASE_COLORS, DEFAULT_ROUTE_COLOR, true);

    // instanceColorStart/instanceColorEnd share one interleaved buffer (LineSegmentsGeometry),
    // packed as one (start, end) colour pair per SEGMENT: for 3 points [A, B, C] that's
    // [A, B, B, C] — 2 segments (A->B, B->C), 4 colour triples. So index 0-2 is point 0 (A)
    // and index 9-11 is point 2 (C, the end colour of the second segment).
    const colorArray = (geometry.attributes.instanceColorStart as THREE.InterleavedBufferAttribute)
      .data.array;
    const climb = new THREE.Color(PHASE_COLORS.climb);
    const cruise = new THREE.Color(PHASE_COLORS.cruise);
    expect(colorArray[0]).toBeCloseTo(climb.r);
    expect(colorArray[1]).toBeCloseTo(climb.g);
    expect(colorArray[2]).toBeCloseTo(climb.b);
    expect(colorArray[9]).toBeCloseTo(cruise.r);
    expect(colorArray[10]).toBeCloseTo(cruise.g);
    expect(colorArray[11]).toBeCloseTo(cruise.b);

    geometry.dispose();
  });

  it("places the anchor at the local origin and other points at their offset in metres", () => {
    const anchor: RoutePoint = { lon: 10, lat: 20, altitude: 1000, phase: "cruise" };
    const other: RoutePoint = { lon: 10.5, lat: 20, altitude: 1000, phase: "cruise" };
    const geometry = buildChunkGeometry(
      anchor,
      [anchor, other],
      PHASE_COLORS,
      DEFAULT_ROUTE_COLOR,
      true,
    );

    const positionArray = (geometry.attributes.instanceStart as THREE.InterleavedBufferAttribute)
      .data.array;
    expect(positionArray[0]).toBeCloseTo(0);
    expect(positionArray[1]).toBeCloseTo(0);
    expect(positionArray[2]).toBeCloseTo(0);

    // 0.5 deg of longitude at 20 deg N is about 52.2 km; the local frame is metres,
    // so a scale regression misses this by orders of magnitude.
    const offset = Math.hypot(positionArray[3], positionArray[4], positionArray[5]);
    const expectedMetres = 0.5 * 111_320 * Math.cos((20 * Math.PI) / 180);
    expect(offset).toBeGreaterThan(expectedMetres * 0.98);
    expect(offset).toBeLessThan(expectedMetres * 1.02);

    geometry.dispose();
  });
});

describe("selectionKey", () => {
  const chunk = (n: number): RoutePoint[] => [{ lat: n, lon: n, altitude: 0 }];

  it("differs for two different unmerged selections of the SAME size — the panning regression", () => {
    // Regression for a real bug (codex review, verified): the old key was
    // `${merged}:${chunks.length}`, so panning from chunks A/B/C into view to D/E/F (same culled
    // count, different actual chunks) produced the SAME key and was wrongly treated as a no-op.
    const fineChunks = [chunk(1), chunk(2), chunk(3), chunk(4), chunk(5), chunk(6)];
    const abc = { chunks: [fineChunks[0], fineChunks[1], fineChunks[2]], merged: false };
    const def = { chunks: [fineChunks[3], fineChunks[4], fineChunks[5]], merged: false };
    expect(selectionKey(fineChunks, abc)).not.toBe(selectionKey(fineChunks, def));
  });

  it("is stable for the identical selection, so a real no-op is still skipped", () => {
    const fineChunks = [chunk(1), chunk(2), chunk(3)];
    const selection = { chunks: [fineChunks[0], fineChunks[1]], merged: false };
    expect(selectionKey(fineChunks, selection)).toBe(selectionKey(fineChunks, selection));
  });

  it("keys the merged branch by count alone — mergeChunks never depends on bounds", () => {
    const fineChunks = [chunk(1), chunk(2)];
    const mergedA = { chunks: [[...chunk(1), ...chunk(2)]], merged: true };
    const mergedB = { chunks: [[...chunk(1), ...chunk(2)]], merged: true }; // different array instance, same shape
    expect(selectionKey(fineChunks, mergedA)).toBe(selectionKey(fineChunks, mergedB));
  });
});

describe("createRouteLine — lineWidthPx", () => {
  it("passes lineWidthPx through to the underlying LineMaterial's linewidth, not silently dropped", async () => {
    // Regression for a real bug (codex review, verified): `RouteLineOptions` used to name this
    // field `linewidthPx` (lowercase w) while `CreateRouteOptions` (index.ts) and `Tier0RouteLine`
    // both use `lineWidthPx` — `{ tier: 1, lineWidthPx: 8 }` compiled fine (both are optional, so
    // TS's excess-property check never triggers on a value passed through a variable) but rendered
    // at the 4 px default, silently. `LineMaterial`'s constructor is intercepted here rather than
    // inspecting a live `Line2` mesh — the whole point is to prove the OPTION reaches the
    // material's `linewidth`, which doesn't need a real WebGL context to observe.
    const capturedOptions: Array<{ linewidth?: number }> = [];
    vi.doMock("three/examples/jsm/lines/LineMaterial.js", () => ({
      LineMaterial: class {
        linewidth: number;
        resolution = { set: vi.fn() };
        constructor(options: { linewidth?: number }) {
          capturedOptions.push(options);
          this.linewidth = options.linewidth ?? 0;
        }
        dispose() {}
      },
    }));
    const { createRouteLine } = await import("./route-line.ts");

    const fakeMap = { addLayer: vi.fn(), getCanvas: () => ({}) } as unknown as Parameters<
      typeof createRouteLine
    >[0];
    createRouteLine(fakeMap, { lineWidthPx: 8 });

    expect(capturedOptions).toHaveLength(1);
    expect(capturedOptions[0].linewidth).toBe(8);
  });
});

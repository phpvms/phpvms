import { describe, expect, it, vi } from "vitest";
import { boundsFor, frameToRoute, onUserTakesControl } from "./camera.ts";

describe("boundsFor", () => {
  it("is undefined for zero points", () => {
    expect(boundsFor([])).toBeUndefined();
  });

  it("collapses to a zero-size box around a single point", () => {
    expect(boundsFor([{ lat: 10, lon: 20 }])).toEqual([20, 10, 20, 10]);
  });

  it("encloses every point, not just the endpoints", () => {
    const points = [
      { lat: 0, lon: 0 },
      { lat: 5, lon: -3 }, // west and north outlier
      { lat: -2, lon: 8 }, // east and south outlier
    ];
    expect(boundsFor(points)).toEqual([-3, -2, 8, 5]);
  });

  it("stays small for a route crossing the antimeridian, instead of spanning the whole globe", () => {
    // Regression for a real bug (codex review, verified): a naive lon min/max reads
    // 179.9°/-179.9° as spanning ~359.8° (west=-179.9, east=179.9, the "long way" through 0°)
    // instead of the ~0.2° these points actually cover — `frameToRoute` would zoom out to fit
    // nearly the whole globe for what is, geographically, a tiny hop across the date line.
    const points = [
      { lat: 10, lon: 179.9 },
      { lat: 10, lon: -179.9 },
    ];
    const bounds = boundsFor(points) as [number, number, number, number];
    const [west, south, east, north] = bounds;
    expect(south).toBe(10);
    expect(north).toBe(10);
    // maplibre's own wrap convention (confirmed against `LngLatBounds.adjustAntiMeridian()`):
    // west > east means "wraps through ±180°, add 360 to east before framing" — the actual span
    // is `(east + 360) - west`, which must be small, not the ~359.8° a naive min/max produces.
    expect(west).toBeGreaterThan(east);
    expect(east + 360 - west).toBeLessThan(30_000 / 111_320); // well under 1 degree
  });

  it("does not wrongly treat an ordinary route (nowhere near the antimeridian) as wrapping", () => {
    const points = [
      { lat: 40, lon: -74 }, // JFK-ish
      { lat: 51, lon: 0 }, // LHR-ish
    ];
    expect(boundsFor(points)).toEqual([-74, 40, 0, 51]);
  });
});

describe("frameToRoute", () => {
  it("calls fitBounds with the route's extent and a pitch", () => {
    const fitBounds = vi.fn();
    const map = { fitBounds } as never;
    frameToRoute(map, [
      { lat: 0, lon: 0 },
      { lat: 1, lon: 1 },
    ]);
    expect(fitBounds).toHaveBeenCalledTimes(1);
    const [bounds, options] = fitBounds.mock.calls[0];
    expect(bounds).toEqual([0, 0, 1, 1]);
    expect(options.pitch).toBeGreaterThan(0);
  });

  it("does nothing for an empty point set — no fitBounds call to jerk the camera around", () => {
    const fitBounds = vi.fn();
    frameToRoute({ fitBounds } as never, []);
    expect(fitBounds).not.toHaveBeenCalled();
  });
});

describe("onUserTakesControl", () => {
  function fakeMap() {
    const handlers = new Map<string, (e: { originalEvent?: unknown }) => void>();
    return {
      on: vi.fn((event: string, handler: (e: { originalEvent?: unknown }) => void) =>
        handlers.set(event, handler),
      ),
      off: vi.fn((event: string) => handlers.delete(event)),
      emit(event: string, payload: { originalEvent?: unknown }) {
        handlers.get(event)?.(payload);
      },
    };
  }

  it("fires on a user-driven movestart (carries an originalEvent)", () => {
    const map = fakeMap();
    const onInteracted = vi.fn();
    onUserTakesControl(map as never, onInteracted);
    map.emit("movestart", { originalEvent: new Event("mousedown") });
    expect(onInteracted).toHaveBeenCalledTimes(1);
  });

  it("does NOT fire for a programmatic movestart (fitBounds/flyTo/jumpTo carry no originalEvent)", () => {
    const map = fakeMap();
    const onInteracted = vi.fn();
    onUserTakesControl(map as never, onInteracted);
    map.emit("movestart", {}); // no originalEvent — this is what fitBounds fires
    expect(onInteracted).not.toHaveBeenCalled();
  });

  it("unsubscribes after firing once — a second user move does not fire again", () => {
    const map = fakeMap();
    const onInteracted = vi.fn();
    onUserTakesControl(map as never, onInteracted);
    map.emit("movestart", { originalEvent: new Event("mousedown") });
    map.emit("movestart", { originalEvent: new Event("mousedown") });
    expect(onInteracted).toHaveBeenCalledTimes(1);
  });

  it("the returned cleanup function stops listening", () => {
    const map = fakeMap();
    const onInteracted = vi.fn();
    const cleanup = onUserTakesControl(map as never, onInteracted);
    cleanup();
    map.emit("movestart", { originalEvent: new Event("mousedown") });
    expect(onInteracted).not.toHaveBeenCalled();
  });
});

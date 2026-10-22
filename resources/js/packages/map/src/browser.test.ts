import { describe, expect, it } from "vitest";
import { cappedPixelRatio, supportsWebGL2 } from "./browser.ts";

describe("cappedPixelRatio", () => {
  it("caps a high DPR (phone-typical) down to 2", () => {
    expect(cappedPixelRatio(3)).toBe(2);
    expect(cappedPixelRatio(4)).toBe(2);
  });

  it("leaves a DPR at or below 2 unchanged", () => {
    expect(cappedPixelRatio(1)).toBe(1);
    expect(cappedPixelRatio(2)).toBe(2);
    expect(cappedPixelRatio(1.5)).toBe(1.5);
  });

  it("falls back to 1 for a non-finite or non-positive reported ratio", () => {
    expect(cappedPixelRatio(0)).toBe(1);
    expect(cappedPixelRatio(-1)).toBe(1);
    expect(cappedPixelRatio(Number.NaN)).toBe(1);
  });
});

describe("supportsWebGL2", () => {
  it("returns a boolean without throwing, whatever this environment supports", () => {
    // happy-dom's canvas has no real WebGL2 implementation, so the environment this runs in
    // does not exercise the "supported" branch — the exact value is environment-dependent and
    // not asserted here. What must hold everywhere is that the probe never throws.
    expect(() => supportsWebGL2()).not.toThrow();
    expect(typeof supportsWebGL2()).toBe("boolean");
  });
});

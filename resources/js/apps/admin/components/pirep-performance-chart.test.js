import { describe, expect, it } from "vitest";
import { withAlpha } from "./pirep-performance-chart.js";

/**
 * Regression: the area fill under each series was built by appending two hex
 * digits to the series colour. That only produces a valid colour for 6-digit
 * hex, and the series colour arrives as `rgb(r, g, b)` from `cssVar`/`toRgb`
 * whenever the theme variable resolves — so the fill was unparseable and
 * Chart.js painted it solid black on the PIREP performance chart.
 */
describe("withAlpha", () => {
  it("makes a translucent rgba() from an rgb() colour — the shape cssVar actually returns", () => {
    expect(withAlpha("rgb(6, 126, 193)", 0.5)).toBe("rgba(6, 126, 193, 0.5)");
  });

  it("never emits the unparseable concatenation that caused the black fill", () => {
    const fill = withAlpha("rgb(6, 126, 193)");
    expect(fill).not.toContain(")2");
    expect(fill.startsWith("rgba(")).toBe(true);
  });

  it("handles a hex colour too — cssVar falls back to hex when the variable is missing", () => {
    // This is the path that accidentally WORKED before, and must keep working.
    expect(withAlpha("#067ec1", 0.5)).toBe("rgba(6, 126, 193, 0.5)");
  });

  it("defaults to the alpha the old two-hex-digit suffix meant", () => {
    expect(withAlpha("rgb(0, 0, 0)")).toBe(`rgba(0, 0, 0, ${34 / 255})`);
  });
});

import { describe, expect, it } from "vitest";
import { blankStyle, esriWorldImageryStyle } from "./basemaps.ts";

describe("blankStyle", () => {
  it("is a self-contained style with no external sources to fetch", () => {
    const style = blankStyle("test-fallback", "#123456");
    expect(style.sources).toEqual({});
    expect(style.layers).toHaveLength(1);
    expect(style.layers[0].type).toBe("background");
  });

  it("uses the given background colour", () => {
    const style = blankStyle("x", "#abcdef");
    expect(
      (style.layers[0] as { paint: { "background-color": string } }).paint["background-color"],
    ).toBe("#abcdef");
  });
});

describe("esriWorldImageryStyle", () => {
  it("themes the background (the globe's sky) differently for light and dark", () => {
    const light = esriWorldImageryStyle("light");
    const dark = esriWorldImageryStyle("dark");
    const bg = (style: typeof light) =>
      (style.layers[0] as { paint: { "background-color": string } }).paint["background-color"];
    expect(bg(light)).not.toBe(bg(dark));
  });

  it("carries real attribution on its tile source", () => {
    const style = esriWorldImageryStyle("light");
    const esriSource = style.sources.esri as { attribution?: string };
    expect(esriSource.attribution).toContain("Esri");
  });

  it("has no glyphs — no symbol layer in this style needs them, and there is no phpVMS font directory to point at yet", () => {
    const style = esriWorldImageryStyle("light");
    expect(style.glyphs).toBeUndefined();
  });
});

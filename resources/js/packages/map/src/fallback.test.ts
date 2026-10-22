import { describe, expect, it } from "vitest";
import { renderStaticFallback } from "./fallback.ts";

describe("renderStaticFallback", () => {
  it("renders the airport pair, not an error string or a blank container", () => {
    const container = document.createElement("div");
    renderStaticFallback(container, { from: { icao: "KJFK" }, to: { icao: "EGLL" } });

    expect(container.textContent).toContain("KJFK");
    expect(container.textContent).toContain("EGLL");
    expect(container.textContent).not.toMatch(/error/i);
    expect(container.children.length).toBeGreaterThan(0);
  });

  it("includes the summary when provided", () => {
    const container = document.createElement("div");
    renderStaticFallback(container, {
      from: { icao: "KJFK" },
      to: { icao: "EGLL" },
      summary: "BA178 · 3,459 nm",
    });
    expect(container.textContent).toContain("BA178");
  });

  it("omits the summary line when none is given, rather than rendering an empty one", () => {
    const container = document.createElement("div");
    renderStaticFallback(container, { from: { icao: "KJFK" }, to: { icao: "EGLL" } });
    expect(container.querySelector(".phpvms-map-fallback__summary")).toBeNull();
  });

  it("replaces previous content on a second call rather than appending", () => {
    const container = document.createElement("div");
    renderStaticFallback(container, { from: { icao: "KJFK" }, to: { icao: "EGLL" } });
    renderStaticFallback(container, { from: { icao: "KLAX" }, to: { icao: "RJTT" } });
    expect(container.textContent).not.toContain("KJFK");
    expect(container.textContent).toContain("KLAX");
  });
});

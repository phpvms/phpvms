import { describe, it, expect, afterEach } from "vitest";
import { installFoucGuard } from "@/elements/fouc-guard";

afterEach(() => {
  document.getElementById("pv-elements-fouc-guard")?.remove();
});

describe("installFoucGuard", () => {
  it("injects a :not(:defined) rule for every given tag", () => {
    installFoucGuard(["pv-hero", "pv-callout"]);

    const style = document.getElementById("pv-elements-fouc-guard");
    expect(style).not.toBeNull();
    expect(style!.textContent).toContain("pv-hero:not(:defined)");
    expect(style!.textContent).toContain("pv-callout:not(:defined)");
    expect(style!.textContent).toContain("visibility: hidden");
  });

  it("is idempotent: a second call does not duplicate the <style> tag", () => {
    installFoucGuard(["pv-hero"]);
    installFoucGuard(["pv-hero"]);

    expect(document.querySelectorAll("#pv-elements-fouc-guard")).toHaveLength(1);
  });

  it("does nothing for an empty tag list", () => {
    installFoucGuard([]);
    expect(document.getElementById("pv-elements-fouc-guard")).toBeNull();
  });
});

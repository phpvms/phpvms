import { describe, it, expect, beforeAll } from "vitest";

// Importing the REAL bundle entry — the same module a bare HTML document's
// <script type="module" src="/build/skylight/elements.js"> would load. No
// @vue/test-utils, no createApp(), no mounted Vue instance in this test file:
// the only "framework" present is what each custom element carries internally
// (`web-component-widgets`: "Element works in plain markup").
beforeAll(async () => {
  await import("@/elements/index");
});

describe("bare HTML document (no Vue app)", () => {
  it("every first-party element upgrades and renders from markup alone", async () => {
    document.body.innerHTML = `
      <pv-hero heading="Skylight">Book your next flight</pv-hero>
      <pv-callout variant="success">Dispatch cleared</pv-callout>
      <pv-stat-group items='[{"label":"Hours","value":"12.3"}]'></pv-stat-group>
    `;

    await Promise.all([
      customElements.whenDefined("pv-hero"),
      customElements.whenDefined("pv-callout"),
      customElements.whenDefined("pv-stat-group"),
    ]);

    const hero = document.querySelector("pv-hero")!;
    const callout = document.querySelector("pv-callout")!;
    const statGroup = document.querySelector("pv-stat-group")!;

    expect(hero.shadowRoot).toBeNull();
    expect(hero.querySelector("h1")?.textContent).toBe("Skylight");
    expect(hero.textContent).toContain("Book your next flight");

    expect(callout.shadowRoot).toBeNull();
    expect(callout.textContent).toContain("Dispatch cleared");

    expect(statGroup.shadowRoot).toBeNull();
    expect(statGroup.textContent).toContain("Hours");
    expect(statGroup.textContent).toContain("12.3");

    document.body.innerHTML = "";
  });
});

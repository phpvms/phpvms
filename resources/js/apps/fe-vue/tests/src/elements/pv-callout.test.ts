import { describe, it, expect, beforeAll } from "vitest";
import { defineElement } from "@/elements/define-element";
import PvCallout from "@/elements/PvCallout.ce.vue";

beforeAll(() => defineElement("pv-callout-test", PvCallout));

async function render(markup: string): Promise<HTMLElement> {
  document.body.innerHTML = markup;
  await customElements.whenDefined("pv-callout-test");
  return document.body.firstElementChild as HTMLElement;
}

describe("PvCallout (pv-callout)", () => {
  it("renders a sensible default with zero attributes set", async () => {
    const el = await render("<pv-callout-test></pv-callout-test>");
    expect(el.textContent).toContain("Callout");
    expect(el.querySelector('[role="note"]')?.className).toContain("border-blue-300"); // info
  });

  it("renders the configured variant and slotted body", async () => {
    const el = await render(
      '<pv-callout-test variant="error">Fuel exceeds tank capacity</pv-callout-test>',
    );
    expect(el.textContent).toContain("Fuel exceeds tank capacity");
    expect(el.querySelector('[role="note"]')?.className).toContain("border-red-300");
  });

  it("falls back to the info variant for an unrecognized value", async () => {
    const el = await render('<pv-callout-test variant="bogus"></pv-callout-test>');
    expect(el.querySelector('[role="note"]')?.className).toContain("border-blue-300");
  });
});

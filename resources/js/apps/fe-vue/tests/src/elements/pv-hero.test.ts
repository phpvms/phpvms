import { describe, it, expect, beforeAll } from "vitest";
import { defineElement } from "@/elements/define-element";
import PvHero from "@/elements/PvHero.ce.vue";

beforeAll(() => defineElement("pv-hero-test", PvHero));

async function render(markup: string): Promise<HTMLElement> {
  document.body.innerHTML = markup;
  await customElements.whenDefined("pv-hero-test");
  return document.body.firstElementChild as HTMLElement;
}

describe("PvHero (pv-hero)", () => {
  it("renders a sensible default with zero attributes set", async () => {
    const el = await render("<pv-hero-test></pv-hero-test>");
    expect(el.querySelector("h1")?.textContent).toBe("Untitled page");
    expect(el.querySelector("p")).toBeNull(); // no eyebrow attribute → no eyebrow line
  });

  it("renders configured heading, eyebrow, and slotted content", async () => {
    const el = await render(
      '<pv-hero-test heading="Route Map" eyebrow="Dispatch">Book a flight</pv-hero-test>',
    );
    expect(el.querySelector("h1")?.textContent).toBe("Route Map");
    expect(el.querySelector("p")?.textContent).toBe("Dispatch");
    expect(el.textContent).toContain("Book a flight");
  });
});

import { describe, it, expect, beforeAll } from "vitest";
import { defineElement } from "@/elements/define-element";
import PvHero from "@/elements/PvHero.ce.vue";

const TAG = "pv-hero-light-dom-test";

beforeAll(() => defineElement(TAG, PvHero));

describe("web-component-widgets: light DOM cross-cutting requirements", () => {
  it("a page stylesheet rule targeting the element's internal structure applies (no shadow root to block it)", async () => {
    const style = document.createElement("style");
    style.textContent = `${TAG} h1 { color: rgb(1, 2, 3); }`;
    document.head.appendChild(style);

    document.body.innerHTML = `<${TAG}></${TAG}>`;
    await customElements.whenDefined(TAG);
    const heading = document.querySelector(`${TAG} h1`)!;

    expect(getComputedStyle(heading).color).toBe("rgb(1, 2, 3)");

    style.remove();
    document.body.innerHTML = "";
  });

  it("a document-wide query from outside the element finds nodes inside it (the third-party-widget mounting case)", async () => {
    document.body.innerHTML = `<${TAG} heading="Nav"></${TAG}>`;
    await customElements.whenDefined(TAG);

    // A script that has never heard of this element still finds its internals
    // via a plain document query — impossible if it rendered into a shadow root.
    expect(document.querySelector("h1")?.textContent).toBe("Nav");

    document.body.innerHTML = "";
  });
});

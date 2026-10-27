import { describe, it, expect } from "vitest";
import { defineComponent, h } from "vue";
import { defineElement } from "@/elements/define-element";

function freshTag(): string {
  return `pv-order-${Math.random().toString(36).slice(2)}`;
}

const Comp = defineComponent({ setup: () => () => h("span", "ok") });

describe("upgrade order (D5: definition and insertion order need no orchestration)", () => {
  it("upgrades when the tag is inserted via innerHTML AFTER the bundle already defined it", async () => {
    const tag = freshTag();
    defineElement(tag, Comp);

    document.body.innerHTML = `<${tag}></${tag}>`;
    await customElements.whenDefined(tag);
    expect(document.querySelector(tag)?.textContent).toBe("ok");

    document.body.innerHTML = "";
  });

  it("upgrades when the tag is inserted via innerHTML BEFORE the bundle defines it", async () => {
    const tag = freshTag();

    // The markup lands first — e.g. the framed document's body parses before
    // its <script type="module"> importing the elements bundle finishes.
    document.body.innerHTML = `<${tag}></${tag}>`;
    const el = document.querySelector(tag)!;
    expect(el.shadowRoot).toBeNull();
    expect(el.textContent).toBe(""); // not upgraded yet — plain unknown element

    defineElement(tag, Comp);
    await customElements.whenDefined(tag);

    // Per the DOM spec, define() upgrades existing candidates already in the
    // tree — the SAME node upgrades in place, no re-insertion needed.
    expect(el.textContent).toBe("ok");
  });
});

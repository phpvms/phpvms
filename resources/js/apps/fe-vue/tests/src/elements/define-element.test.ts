import { describe, it, expect } from "vitest";
import { defineComponent, h } from "vue";
import { defineElement } from "@/elements/define-element";

function freshTag(): string {
  return `pv-test-${Math.random().toString(36).slice(2)}`;
}

describe("defineElement", () => {
  it("registers a custom element that renders and attaches no shadow root", async () => {
    const tag = freshTag();
    const Comp = defineComponent({ setup: () => () => h("p", "hi") });
    defineElement(tag, Comp);

    document.body.innerHTML = `<${tag}></${tag}>`;
    await customElements.whenDefined(tag);
    const el = document.body.querySelector(tag)!;

    // Light DOM (D1): no shadow root, and content is directly queryable.
    expect(el.shadowRoot).toBeNull();
    expect(el.querySelector("p")?.textContent).toBe("hi");

    document.body.innerHTML = "";
  });

  it("is idempotent: registering the same tag twice does not throw", () => {
    const tag = freshTag();
    const Comp = defineComponent({ setup: () => () => h("span") });

    expect(() => {
      defineElement(tag, Comp);
      defineElement(tag, Comp);
    }).not.toThrow();
  });
});

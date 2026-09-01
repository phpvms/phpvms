import { defineCustomElement, type Component } from "vue";

/**
 * Register a purpose-built `.ce.vue` component as a light-DOM custom element
 * (`web-component-widget-floor` D1).
 *
 * `shadowRoot: false` is load-bearing, not a default left in place: with a
 * shadow root, third-party widget scripts that `document.querySelector` for
 * their mount point would never find it, global Tailwind (the elements' only
 * styling) could not reach inside, and Reka's portals (which teleport overlay
 * content to `body`) would already be outside it anyway. With `shadowRoot:
 * false` Vue also drops its own style injection — there is nothing to inject,
 * since these components carry no SFC `<style>` block.
 *
 * Idempotent: a document that loads this bundle twice (e.g. two `srcdoc`
 * frames sharing a cached module) does not throw on the second `define()`.
 */
export function defineElement(tag: string, component: Component): void {
  if (customElements.get(tag)) {
    return;
  }

  const ElementCtor = defineCustomElement(component, { shadowRoot: false });
  customElements.define(tag, ElementCtor);
}

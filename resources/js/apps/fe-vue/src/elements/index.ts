/**
 * Custom-elements bundle entry (`web-component-widget-floor`). Defines every
 * first-party widget that is ALSO consumable as a light-DOM custom element —
 * from any framework, or from plain HTML with no framework at all.
 *
 * Built through the same `@nuxt/ui/vite` plugin as the main SPA (an element
 * MAY render Nuxt UI internally; its internals resolve `#imports` and
 * `#build/ui/*`, which only that plugin serves), but as a SEPARATE Rollup
 * entry that bundles its own copy of Vue's runtime-only build rather than
 * externalizing `vue` — a bare document has no import map to resolve a shared
 * one from (`vite.config.ts`'s `external()`).
 *
 * Convention: an authorable element is a `.ce.vue` single-file component in
 * this directory — attributes for scalar configuration, the default slot for
 * composition, no named/scoped slots (Vue's light-DOM slot emulation has open
 * upstream bugs — vuejs/core#13208), and a visible diagnostic (not a thrown
 * error or blank output) if a JSON-encoded list attribute is malformed. See
 * `PvStatGroup.ce.vue` for that last case. Register it below via
 * `defineElement(tag, Component)`.
 *
 * Do NOT wrap a Nuxt UI component directly as one of these elements (D2).
 * Custom-element attributes are strings and Vue does not parse them —
 * `@vue/runtime-dom` casts `Number`/`Boolean` props only and has no
 * `JSON.parse` at all — but Nuxt UI's API is object/array-shaped throughout
 * (e.g. `NavigationMenu`'s `items: { type: null }`), so an attribute would
 * hand it a raw string. Its providers (locale, portal target, Reka's
 * `ConfigProvider`/`TooltipProvider`/`UOverlayProvider`) also don't cross the
 * custom-element boundary — each wrapped element would need its own `<UApp>`.
 * An element MAY render Nuxt UI internally; it must not expose Nuxt UI's prop
 * shape as its own attribute API.
 */
import type { Component } from "vue";
import { defineElement } from "./define-element";
import { installFoucGuard } from "./fouc-guard";
import PvHero from "./PvHero.ce.vue";
import PvCallout from "./PvCallout.ce.vue";
import PvStatGroup from "./PvStatGroup.ce.vue";

const ELEMENTS: Record<string, Component> = {
  "pv-hero": PvHero,
  "pv-callout": PvCallout,
  "pv-stat-group": PvStatGroup,
};

installFoucGuard(Object.keys(ELEMENTS));

for (const [tag, component] of Object.entries(ELEMENTS)) {
  defineElement(tag, component);
}

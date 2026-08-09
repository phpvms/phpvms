# Admin theme — Wayfinding

HTML demo of the chosen visual world for the admin panel redesign (before porting to
`resources/css/filament/admin/theme.css`). Open `index.html` in a browser; the **Night**
button in the topbar toggles the dark terminal.

## Design stance

The admin as airport wayfinding: every screen a sign tree, the active location in
follow-me yellow (#FFD500), arrows as first-class glyphs. White sign slabs with 1.5px ink
borders on a concourse-gray ground; square corners; no decorative shadows. JetBrains Mono
carries aviation data (idents, routes, times); Hanken Grotesk carries everything else.
State colors (go-green, caution-amber, stop-red) are strictly functional. Dark mode is the
same terminal at night — ink ground, dimmed slabs, same yellow.

## Key choices

- One yellow field per view: active nav sign, focused input, or the item needing action
- Buttons are ink slabs that light up yellow on hover; danger is the only colored slab
- Inputs get full ink borders; focus fills the field yellow-tinted; invalid = stop-red
  border + mono uppercase error; disabled = dashed border
- Tabs are gate signs; the active tab wears yellow, counts are ink chits
- Sidebar items are sign slabs; the active sign wipes yellow once on load (reduced-motion
  aware), arrows nudge on hover
- Stat "signs" instead of metric cards; the queue needing review is the yellow one

## Trade-offs

- Strong at: orientation, scannability, a distinctive identity no stock Filament panel has;
  dark mode is arguably better than light (night ops)
- Weak at: full ink borders on every field is visually loud on very long forms; the port
  may tune border weight on dense edit pages without breaking the grammar

## Port notes (for the Filament implementation)

- Filament `primary` palette becomes ink (near-black); yellow lives as custom
  `--color-signal-*` tokens applied via CSS (active nav, focus, highlights)
- Global radius override to ~0 on sections/buttons/inputs; semantic badge colors unchanged
- Stock Filament primitives (tables, modals, notifications) get the slab treatment so
  module-injected UIs inherit the world automatically

## <!-- SEED: established with the user before implementation; re-run $impeccable document once there's code to capture the actual tokens and components. -->

name: phpVMS Admin
description: Airport wayfinding as an operations admin — sign slabs, follow-me yellow, arrows as first-class glyphs.

---

# Design System: phpVMS Admin

## Overview

**Creative North Star: "The Terminal Sign Tree"**

The admin panel is an airport's wayfinding system: every screen reads as a tree of signs,
the active location always wears the follow-me yellow, and arrows point onward to the next
task. Surfaces are sign slabs — white, ink-bordered, rectilinear. Color is disciplined:
ink and yellow carry navigation; green, amber, and red appear only when a flight, PIREP,
or system state demands them. The effect is an ops tool that orients like an airport, not
another indigo SaaS dashboard.

Chosen over (eliminated by review): Standards Manual (orange masthead register), Dispatch
Office (EFB-soft blue SaaS), OFP Release (stamped dispatch document), Ops Wallboard (dark
masthead status cells), Sectional Chart (cartographic zone tints).

**Key Characteristics:**

- Sign slabs: white surfaces, 1.5px ink border, square corners, no decorative shadow
- Follow-me yellow (#FFD500) marks the active location and primary focus — always with ink text
- Arrows (→) are first-class glyphs for onward navigation and active titles
- JetBrains Mono for aviation data (codes, times, idents); Hanken Grotesk for everything else
- Functional state colors only: go-green, caution-amber, stop-red
- Dark mode is "the terminal at night": ink ground, dimmed slabs, same yellow

## Colors

Palette strategy: restrained neutrals + one committed signal color + functional state colors.

### Primary

- **Signal Yellow** (#FFD500): active navigation, focused fields, the item demanding action. Never decorative; never with white text.
- **Ink** (#16181A): primary actions, key borders, display text.

### Neutral

- **Concourse Ground** (#F4F4F2 light / #0F1113 dark): the page floor beneath the slabs.
- **Slab White** (#FFFFFF light / #16191C dark): sign surfaces.
- Secondary text and tertiary labels tint from ink, not gray-blue.

### Tertiary (functional states)

- **Go Green** (#14854F), **Caution Amber** (#B97A00), **Stop Red** (#C81E1E): state chips, alerts, validation — nothing else.

### Named Rules

**The Follow-Me Rule.** Exactly one yellow field per view: the active sign, the focused
field, or the single item needing action. Two competing yellows is a wayfinding bug.
**The Signal Discipline Rule.** Green/amber/red exist only as operational state. If a color
isn't reporting a state, it's ink or yellow.

## Typography

**Display/Body Font:** Hanken Grotesk (the free Frutiger descendant — airport-signage lineage)
**Label/Mono Font:** JetBrains Mono — aviation data only: idents, ICAO codes, times, register labels

### Hierarchy

- **Display** (800, -0.02em): page titles, may carry a trailing arrow
- **Title** (700): panel/section headings
- **Body** (400–600, 14px/1.45): UI text, measure ≤ 72ch
- **Label** (JetBrains Mono 600, 10–10.5px, +0.12em, uppercase): field labels, register keys, table headers

## Layout

Document-page composition: sign-slab topbar, sign-tree sidebar, content pages with generous
separation between panels. Panels are slabs, not cards-in-cards. Numbers align tabular;
times and codes set in mono.

## Elevation & Depth

Flat by doctrine. Depth is the ink border alone; shadows appear nowhere at rest. A lifted
state (dropdown, modal) may use one hard, small offset shadow (sign-posted-over-sign), never
a soft diffuse halo.

## Shapes

Rectilinear. Signs are square-cornered; chips, buttons, inputs, and tables follow. The only
round forms are the user avatar and live-status dots.

## Do's and Don'ts

### Do:

- **Do** put the arrow on the active path: nav items, breadcrumbs, onward links.
- **Do** render flight idents, routes, and times in JetBrains Mono with tabular figures.
- **Do** keep one yellow field per view.
- **Do** honor both modes: light terminal by day, ink terminal by night, same sign grammar.

### Don't:

- **Don't** scatter the yellow as accent borders, icons, or underlines — it's a field, not a stroke.
- **Don't** add gradients, glass, or soft card shadows.
- **Don't** use state colors (green/amber/red) decoratively.
- **Don't** round the slabs.

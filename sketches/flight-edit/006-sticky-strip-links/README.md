# Variant 6 — Sticky Strip with Section Links

## Design stance

One sticky bar on top carries both the flight identity (code, route, status, stats, Delete) AND the section links (Flight / Schedule / Route / Subfleets / Fields / Fares). No left column — the strip is the single navigation anchor, and it stays pinned while you scroll through the stacked RouteForge-style sections.

## Key choices

- Strip row 1: AP2131 · KATL → KAUS · Enabled pill · flight time/distance/level/subfleet counts · Delete
- Strip row 2: section links with numbers + counts, scrollspy highlights the current section
- Whole strip is `position: sticky` (top 12px, z-index above sections), so links stay reachable mid-form
- Sections: RouteForge style (thin bordered boxes, uppercase tracking titles, hints under fields)
- scroll-margin-top on sections accounts for the pinned strip so anchor jumps don't hide the heading

## Trade-offs

- Strong at: one place for orientation — identity and navigation never leave the viewport; no wasted left rail width; works great with full-width content
- Weak at: the strip is taller (two rows) — a bit more chrome pinned at top; on narrow screens the meta may need to hide

## Best for

- Flight + bundle edit pages: identity-led, navigation always visible, RouteForge-consistent

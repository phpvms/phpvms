# Variant 4 — Hybrid (Identity Bar + Sticky Section Nav)

## Design stance

The card-grid's identity strip replaces the page heading on top; below it, the stacked-section layout with a sticky left section-nav that highlights on scroll. The "at a glance" top bar + linear scroll with orientation.

## Key choices

- Identity strip (AP2131 · KATL → KAUS · Enabled · flight time/distance/level/subfleet counts) replaces the "Edit AP2131" heading — the key facts lead the page
- Delete action moved into the identity strip
- Sticky section nav on the left (Flight / Schedule / Route / Subfleets / Fields / Fares) with counts, scrollspy highlights the current section
- All sections stacked and expanded — nothing hidden behind tabs or collapses

## Trade-offs

- Strong at: identity at a glance, orientation while scrolling, no tab juggling, works on wide full-width pages without wasting width
- Weak at: still a long scroll on dense entities; the identity strip needs real data (route, times, counts) to be useful — on sparse records it thins out

## Best for

- The flight edit page (and other edit pages) where the record has a strong identity worth surfacing

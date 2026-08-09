# Variant 2 — Stacked + Section Nav

## Design stance

One long scrollable page — form sections and related tables in order — with a sticky left section-nav that highlights as you scroll. The classic "long form with anchor nav" pattern.

## Key choices

- Layout: single column of cards, 190px sticky nav rail on the left with numbered sections
- All sections (Flight, Schedule, Route, Subfleets, Fields, Fares) always expanded — nothing hidden
- Scrollspy highlights the current section in the nav
- Save/Cancel in a sticky bottom action bar so they're always reachable

## Trade-offs

- Strong at: linear task flow, everything discoverable, section nav gives orientation in a long page
- Weak at: longer scroll than split-pane; related tables sit far below the form fields

## Best for

- Users who edit one flight deeply and want full context while scrolling

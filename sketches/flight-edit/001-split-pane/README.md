# Variant 1 — Split Pane

## Design stance

The flight form owns the left two-thirds of the page; related data (subfleets, fields, fares) lives in a compact right rail that's always visible — no tabs, no tab juggling.

## Key choices

- Layout: two-column split (form 3fr / rail 2fr), rail stacks three collapsible cards
- Form sections (Scheduling, Route) collapse inline under the Flight Information card
- Rail cards show count badges and stay open/closed independently
- Save/Cancel pinned to the bottom of the form card

## Trade-offs

- Strong at: form + related data visible at once; no clicks to reach subfleets/fares
- Weak at: the right rail squeezes on narrower screens (collapses to single column < 1100px); dense pages may feel busy

## Best for

- Users who frequently attach/detach subfleets or fares while editing a flight

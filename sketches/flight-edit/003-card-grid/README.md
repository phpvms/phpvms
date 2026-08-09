# Variant 3 — Card Grid

## Design stance

Dashboard-style card grid: a compact identity strip on top (flight code, route, status, key stats), then equal-weight cards for Flight / Schedule / Route / Subfleets / Fields / Fares in a 2-column grid — everything on one screen.

## Key choices

- Layout: 2-column card grid, identity strip spans the top
- Denser card heads with icons + count badges; tables sit flush in cards
- Remarks card spans full width at the bottom
- Save/Cancel below the grid

## Trade-offs

- Strong at: overview density, quick scan of everything at once, matches the dashboard visual language
- Weak at: cards can feel cramped with many fields; hierarchy flatter than split-pane

## Best for

- Users who want the "at a glance" operations feel, consistent with the admin dashboard

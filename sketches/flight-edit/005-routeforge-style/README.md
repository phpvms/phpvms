# Variant 5 — RouteForge Style

## Design stance

Replicates the RouteForge page's visual language on the flight edit page: thin bordered boxes with uppercase tracking-wide gray section titles, compact inputs, and a hint paragraph under every field. Everything explained inline, tight spacing, easy to scan.

## Key choices

- Boxes: `border border-gray-200 rounded p-4` (RouteForge's SectionShell) — lighter than the card/shadow look
- Section titles: uppercase, small, letter-spaced, gray (exactly RouteForge's `text-sm font-semibold uppercase tracking-wide text-gray-500`)
- Fields: compact `px-3 py-2 text-sm` inputs with primary focus ring; hint text (`text-xs text-gray-500`) under fields that need explanation
- Kept from the hybrid: identity strip on top (replaces the page heading) + sticky section nav with scrollspy + Delete in the strip
- Related data (Subfleets / Fields / Fares) as RouteForge-style sections in the same stacked column

## Trade-offs

- Strong at: consistent with an existing admin page the user already likes; very legible; hints make fields self-documenting
- Weak at: hints add vertical length; not every field needs one (use sparingly)

## Best for

- The flight edit page, and then the bundle edit page — one shared visual language across the Flights area

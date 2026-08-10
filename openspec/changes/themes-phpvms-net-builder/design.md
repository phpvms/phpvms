## Context

The phpVMS runtime theme change defines the public versioned document,
normalization rules, curated component settings, and preview contract. The
authoring experience at themes.phpvms.net needs separate source ownership and
delivery because it is a standalone web application, not a pilot-frontend
route or a phpVMS runtime concern.

The starting point is the MIT-licensed Nuxt UI Theme Builder. The builder must
retain its upstream editing strengths while adding phpVMS settings and previews
without exposing unstable Nuxt UI internals as phpVMS public data.

## Goals / Non-Goals

**Goals:**

1. Provide a standalone visual editor for the versioned phpVMS theme document.
2. Import raw upstream Theme Builder JSON and complete phpVMS documents.
3. Preview actual Nuxt UI controls and focused phpVMS pilot-frontend domain
   components in light and dark modes.
4. Keep normal controls curated and validate all import/export payloads against
   the phpVMS runtime contract.

**Non-Goals:**

1. Implementing phpVMS database persistence, CSS generation, or production
   theme publication.
2. Embedding the builder into phpVMS or deploying its source from this repo.
3. Supporting arbitrary Tailwind classes or arbitrary Nuxt UI slot classes.
4. Replacing the core runtime normalizer or making the builder the source of
   truth for schema versions.

## Decisions

### D1 — The builder remains a separate application and OpenSpec scope

Create and maintain themes.phpvms.net in its own repository with its own
OpenSpec root. This change is the hand-off specification until that repository
exists; implementation tasks move with the builder repository. phpVMS core
owns the API/schema contract and runtime publication, while the builder owns
editor UX, local draft state, import/export, and preview presentation.

### D2 — Fork upstream editor; add a phpVMS adapter layer

Keep upstream editor features for palette, neutral, font, radius, light/dark
configuration, and Nuxt UI token overrides. Add a phpVMS adapter that maps the
editor state to the public document: `nuxtUi.theme`, curated
`nuxtUi.components`, and `phpvms` namespaces. Upstream state stays an input to
the adapter, not an alternative public export format.

### D3 — Curated controls use stable identifiers

The builder presents semantic choices for button shape/variant, input
appearance, density, navigation, tables, and domain tokens. It exports stable
identifiers, numbers, colors, fonts, and CSS-variable values defined by the
core schema. It does not include a generic class-name field or raw Nuxt UI
slot-class JSON editor.

### D4 — Preview uses the core contract

The preview renders the real Nuxt UI controls plus a focused set of domain
components and routes supplied by the phpVMS preview integration contract. It
uses the normalized document, generated preview CSS, and the version-compatible
frontend resolver contract to produce `UTheme` data; PHP does not reproduce
Nuxt UI's internal configuration shape. Unsaved edits are applied in builder
memory only; core publishing remains an explicit separate action.

### D5 — Import is forgiving at the format boundary, strict at export

The builder recognizes raw Theme Builder JSON and versioned phpVMS documents.
It shows migration/validation errors in the editor, fills only schema-defined
defaults, and exports the current phpVMS versioned format. The core performs
the authoritative validation again on import or publish.

## Risks / Trade-offs

1. [Upstream builder changes] → isolate the adapter layer and test import/export
   fixtures against the selected upstream release.
2. [Preview drift from phpVMS] → use contract-supplied rendered components or
   a versioned preview package rather than recreating component markup.
3. [Builder output bypasses runtime validation] → core remains authoritative and
   rejects invalid publication input.
4. [Unbounded customization leaks internal classes] → limit normal controls to
   schema identifiers and route advanced CSS to the core `custom.css` field.

## Migration Plan

1. Initialize the separate builder repository and move this change there.
2. Fork and license the upstream Theme Builder, preserving required notices.
3. Add the adapter, fixtures, curated phpVMS panels, import/export, and preview.
4. Integrate against a versioned core preview endpoint or package.
5. Verify all exported documents through the core normalizer before release.

## Open Questions

1. Which repository will own themes.phpvms.net and its deployment credentials?
2. Will real component preview use a hosted phpVMS preview endpoint, a
   versioned component package, or both?
3. Which domains are allowed to call the preview contract and how is access
   authenticated?

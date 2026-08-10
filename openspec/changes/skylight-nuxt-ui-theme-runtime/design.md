## Context

`skylight-vue-only-floor` is complete but remains unarchived. Its delta spec is
synced into the main `vue-theme-floor` spec so this change has a valid base to
modify. Its Vue-only,
Vite, Inertia, persistent `PvApp`, Vue slot registry, and domain-component
boundaries remain valid. Its shadcn-vue primitive requirement does not: the
current architecture contract names Nuxt UI as the generic Vue component
library and makes phpVMS responsible for its own durable theme API.

The application currently builds CSS with Vite and the Skylight Blade shell
emits only the manifest stylesheet. `HandleInertiaRequests::version()` keys on
the manifest modification time, and the saved dark-mode choice is applied by
Vue after startup. There is no published theme record, generated runtime
stylesheet, initial resolved `UTheme` data, or safe Theme Builder import path.

This change must preserve the dirty worktree. In particular, it must not assume
the untracked architecture contract, the unrelated composer change, or the
existing lockfile change are part of its implementation.

## Goals / Non-Goals

**Goals:**

1. Keep the client Vue, Vite, and Inertia; add Nuxt UI only as a Vue library.
2. Make phpVMS the owner of a versioned, portable runtime-theme document.
3. Import raw Nuxt UI Theme Builder JSON safely, normalize it, and apply the
   saved output before first paint.
4. Keep `--pv-*` and stable `.pv-*` hooks as the domain component API while
   resolving generic Nuxt UI configuration internally.
5. Prove the integration with the dashboard toolbar while keeping its current
   size, labels, behavior, and visual appearance.

**Non-Goals:**

1. Adding the Nuxt framework, Nuxt routing, SSR, or a Nuxt application.
2. Creating phpVMS wrappers for generic Nuxt UI controls.
3. Treating arbitrary Tailwind classes or arbitrary Nuxt UI slot-class objects
   as runtime theme input.
4. Building themes.phpvms.net in this repository. The core defines the import,
   export, and preview contract; the builder implementation needs its own
   repository and OpenSpec change.
5. Replacing every pre-contract generic control or redesigning the pilot
   frontend in this first slice. New and refactored generic controls follow the
   Nuxt UI ownership rule immediately.

## Decisions

### D1 — Nuxt UI is the generic control floor, not a framework dependency

Add `@nuxt/ui`, configure its Vite plugin for Inertia, and install its Vue
plugin at the existing frontend bootstrap seams. The persistent `PvApp` hosts
`UApp` and `UTheme`, with the resolved theme passed on its first render. New
and refactored generic controls use Nuxt UI directly at the call site, with
explicit instance data kept at the call site. Existing generic controls may
remain until their named migration slice. `Pv*` remains reserved for
aviation/application domain components and extension seams.

The first converted site is the dashboard toolbar's Add widget, Reset, and
Customize/Done controls. Each receives `UButton` directly and a stable
dashboard hook, while application CSS preserves the existing 32px control
geometry. This proves the provider, resolver, and CSS model without changing
the menu item or unrelated input/table families.

Alternatives considered:

1. Keep shadcn-vue. Rejected because the architecture contract explicitly
   selects Nuxt UI.
2. Add `PvButton`/`PvInput` adapters. Rejected because this would make Nuxt UI
   details a phpVMS styling abstraction and contradict the direct-control rule.
3. Convert all controls in one change. Rejected because it would mix a runtime
   architecture migration with a broad visual rewrite.

### D2 — phpVMS owns the versioned theme document and normalizer

The persisted document has an explicit `version` and separate namespaces:
`nuxtUi.theme` stores the normalized upstream builder configuration,
`nuxtUi.components` stores phpVMS-curated generic-control settings, and
`phpvms` stores domain tokens/settings. The normalizer accepts either this
versioned document or raw JSON exported by nuxt-ui-themes.com. Raw input is
placed under `nuxtUi.theme`; absent phpVMS and component fields receive
versioned defaults. Future document versions require explicit migrations.

The parser validates object shape, known component setting identifiers, value
ranges, supported palette/font/token fields, and custom CSS size. It rejects
unknown or newer document versions, unknown executable content, and any
arbitrary Tailwind-class or unrestricted slot-class input. Invalid input
reports field-level errors and never changes the published theme.

Core import and preview are transient operations: they return a normalized
document, validation diagnostics, generated preview CSS, and resolved preview
input without persisting a draft. Only publish creates durable revision data.
The separate builder owns its local draft state.

Alternatives considered:

1. Persist raw builder JSON as the public contract. Rejected because upstream
   format changes would become unversioned phpVMS breaking changes.
2. Store the document in the existing settings value. Rejected because that
   storage is a string setting and lacks a published-theme revision boundary.

### D3 — Curated settings resolve to internal UTheme output

Public JSON records stable identifiers such as button shape, control density,
input appearance, and surface treatment. A typed frontend resolver maps those
identifiers to already-compiled Nuxt UI `props` and `ui` values, and produces a
complete `ResolvedTheme` for `UTheme`. It is the compatibility layer between
the phpVMS contract and Nuxt UI internals.

Continuous runtime values use generated CSS variables: palette, font family,
radius, font scale, heights, and spacing. Finite choices use exhaustive source
mappings. A resolver change caused by a Nuxt UI upgrade does not change the
public document without a version migration.

### D4 — A published revision is the atomic runtime-theme boundary

Create immutable published-theme revision records globally scoped by rendered
theme name. phpVMS currently selects one renderer globally; tenant or
airline-specific publications require a later schema change. Each revision
holds the schema version, normalized document JSON, optional custom CSS,
content-derived immutable revision, and publication time. A serialized publish
operation validates and normalizes first, renders both browser stylesheets,
writes immutable revision paths, then changes the active revision pointer in a
database transaction. A failed write, failed pointer update, or competing
publish leaves one complete revision active.

Theme assets use a dedicated configurable asset disk that must return
anonymously readable immutable URLs. The default local implementation uses
public storage. A private remote disk must be exposed through an unauthenticated
application delivery route with immutable cache headers; private object URLs
cannot be placed directly in blocking stylesheet links.

`theme.css` contains only `:root`/`.dark` runtime `--ui-*` and `--pv-*`
declarations plus approved font values. It contains no layout rules, Tailwind
directives, or Vue component styles. `custom.css` is the unrestricted advanced
escape hatch but is separately stored and published. The content revision is
included in stylesheet URLs and in the Inertia version calculation.

Alternatives considered:

1. Write mutable files under `public/build`. Rejected because build output is
   deployment output and not the persistent public theme store.
2. Update fixed `theme.css` then invalidate a query string. Rejected because a
   reader could observe one updated stylesheet before the other.

### D5 — Blade supplies pre-paint tokens; Inertia supplies first-render props

Refactor the Skylight shell to emit application CSS, generated `theme.css`,
and generated `custom.css` as separate blocking links in that exact order,
before the module script. The development path must also emit application CSS
as a blocking Vite-served link rather than relying on the JavaScript import.
The shell renders the saved mode class on `<html>`; a small synchronous head
script handles the existing stored light/dark/auto preference before the links.

`HandleInertiaRequests` shares the validated normalized public document and
combines the active publication revision with the frontend manifest revision.
The typed frontend resolver synchronously maps its curated component settings
to `UTheme` before the first Vue render. The stylesheet supplies palette values
before paint; the initial props supply normalized component settings without
making PHP reproduce Nuxt UI's internal configuration shape.

### D6 — Domain hooks and ejection remain stable

Document the supported `--pv-*` variables and meaningful `.pv-*` hooks for
the converted toolbar and existing shell/domain concepts. Domain components
stay focused Vue components with typed props, events, and slots. An airline can
eject one domain component and build its own frontend distribution; this is
the structural escape hatch, separate from runtime theming. Theme authors use
`custom.css` against documented hooks and must not depend on Nuxt UI internal
markup, data attributes, or private class names.

### D7 — Builder integration is a contract, not an implementation task

The core exposes documented import/export schema validation and a preview
payload containing the normalized document, resolved theme, CSS URLs or
generated preview CSS, and representative Skylight routes/components. The
external builder may submit raw Theme Builder JSON and consume this contract.
It is not created, deployed, or maintained by this change.

### D8 — The custom Skylight build and shared Vue runtime remain intact

Nuxt UI integrates into the existing theme-specific Vite build rather than
replacing it with Laravel's default Vite layout. Production continues to emit
the Skylight manifest, externalize Vue, publish the shared Vue vendor module,
build addons, and load host and addon code through one import map. Acceptance
uses built production assets because development currently has a documented
two-Vue limitation for external addons.

## Risks / Trade-offs

1. [Nuxt UI version integration can differ from the current Vite setup] → pin
   the verified package set, typecheck, unit-test, and production-build before
   removing old direct dependencies.
2. [A published stylesheet can become stale in an open Inertia tab] → version
   both the immutable URLs and Inertia asset version with the publication
   revision.
3. [A dark-mode hard reload can flash] → assert the `html` class and stylesheet
   order in a browser hard-reload test before accepting the change.
4. [Raw builder JSON can drift] → normalize behind schema versions, retain
   known fields only, and test current raw export fixtures.
5. [Nuxt UI migration alters the toolbar appearance] → keep the existing
   toolbar geometry CSS and compare desktop/mobile browser captures to the
   current route before expanding the slice.
6. [Custom CSS can override unintentionally] → load it only as the documented
   last escape hatch and test one supported `.pv-*` override.
7. [A configured asset disk may be private] → require anonymous HTTP delivery
   and test the generated stylesheet URLs without an authenticated session.

## Migration Plan

1. Add the globally renderer-scoped data model, schema, transient normalizer
   and preview path, CSS renderer, public publication
   service, admin import/publish path, and backend tests without changing the
   active frontend output.
2. Add Nuxt UI at the existing Vite/Vue seams and introduce typed theme parsing
   and resolution with frontend tests.
3. Change the Blade and Inertia paths to consume a published revision and
   normalized document, with fallback tokens when no published record exists.
4. Convert the three dashboard-toolbar buttons and preserve current hooks and
   CSS geometry. Add unit and browser coverage.
5. Remove shadcn-only source/config/dependencies only after repository-wide
   import checks, typecheck, tests, and production build pass.
6. Deploy the migration with a default normalized theme revision. Rollback
   selects the prior published revision and retains prior immutable assets;
   code rollback leaves the fallback application tokens usable.

## Open Questions

1. Confirm the exact supported raw-export fields from the target
   nuxt-ui-themes.com release while implementing the normalizer; do not infer
   fields beyond a captured fixture.
2. Confirm the admin authorization and UI location for import, preview, and
   publish before adding routes or Filament resources.

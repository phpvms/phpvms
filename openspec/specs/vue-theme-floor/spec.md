# vue-theme-floor Specification

## Purpose

Define the Vue-native pilot frontend floor, its persistent Inertia shell,
extension registry, generic component library, runtime tokens, and state
boundaries.

## Requirements

### Requirement: PvApp outer container

The theme floor SHALL provide `PvApp.vue`, an outer container used as the Inertia **persistent
layout** so it wraps every page and survives navigations. `PvApp` SHALL: (a) provide app-wide
context to descendants via `provide`/`inject` — the slot registry and the current page props (for
`@`-ref resolution); (b) host shadcn overlay mount points (toaster/dialog portals) above the
layout; and (c) render a `PvLayout` with the page content in its `main` region. It MUST NOT be a
web component.

#### Scenario: Persistent wrapper survives navigation

- **WHEN** the user navigates between Inertia pages under the skylight theme
- **THEN** `PvApp` and its provided context/overlays persist (not remounted) while the page content swaps

#### Scenario: Provides registry + page props

- **WHEN** a descendant (`PvLayout`, `PvSlot`, a widget) injects the slot registry or page props
- **THEN** it receives them from `PvApp` without prop-drilling

### Requirement: PvLayout Vue grid shell

The floor SHALL provide `PvLayout.vue`, a Vue component rendering a CSS-grid layout with named
`<slot>`s for `header`, `navigation`, `main`, `aside`, and `footer`. It SHALL expose `navPosition`
(`left`|`right`) and header/aside visibility as props, and drive its grid via a `--pv-page-areas`
CSS custom property so the layout restyles at runtime with no rebuild. It MUST NOT be a web
component and MUST NOT use shadow DOM. `PvApp` renders a `PvLayout`; alternate layout variants MAY
exist and be swapped under the same `PvApp`.

#### Scenario: Regions render in a grid

- **WHEN** content is placed in the `header`, `main`, and `aside` slots of `PvLayout`
- **THEN** those regions render in their grid areas (not stacked) per the layout config

#### Scenario: Runtime layout restyle

- **WHEN** `--pv-page-areas` (or `navPosition`) is changed at runtime
- **THEN** the layout updates with no rebuild

### Requirement: PvSlot renders Vue components from the data registry

The floor SHALL provide `PvSlot.vue`. Given a slot `name`, it SHALL read the slot registry (data:
`{ slot, component, order, props }[]`), filter by `name`, and render each entry's Vue `component`
via `<component :is>` in ascending `order`. String prop values beginning with `@` SHALL be resolved
against the page's props (e.g. `@currentAirport` → the `currentAirport` prop) before being passed.
When a registered component cannot be resolved, it SHALL render a visible fallback, never a blank gap.

#### Scenario: Ordered Vue widget render

- **WHEN** `PvSlot name="dashboard.sidebar"` reads a registry with two entries
- **THEN** it renders both Vue components in ascending `order` with resolved props

#### Scenario: @-ref prop resolution

- **WHEN** a registry entry has `props: { icao: "@currentAirport" }` and the page prop `currentAirport` is `"KJFK"`
- **THEN** the rendered widget receives `icao="KJFK"`

#### Scenario: Missing component fails visibly

- **WHEN** a registry entry names a component that cannot be resolved
- **THEN** `PvSlot` renders a visible diagnostic in place, not an empty region

### Requirement: Vue widgets, no web components

Widgets SHALL be Vue components registered in the slot registry via a `component` reference. The
theme SHALL NOT use web components, `customElements`, `defineCustomElement`, or shadow DOM for
first-party widgets. The existing weather widget SHALL be a Vue component registered in the
`dashboard.sidebar` slot, delivering data via its own endpoint so it never blocks first paint and
failing visibly when the provider is unavailable.

#### Scenario: Weather widget as a Vue component

- **WHEN** the dashboard renders under the skylight theme
- **THEN** the weather widget is a Vue component mounted by `PvSlot`, fetching `/api/weather/{icao}` for the resolved ICAO

#### Scenario: First paint not blocked

- **WHEN** the weather provider is slow
- **THEN** the dashboard first-paints immediately and the widget fills in when its fetch resolves; on failure it shows a visible diagnostic

### Requirement: shadcn-vue primitives and Tailwind with token bridge

The theme SHALL use shadcn-vue (reka-ui + Tailwind + CVA) as its component primitives, with component
source owned in `apps/spa/components/ui/`. Tailwind v4 SHALL be integrated via `@tailwindcss/vite`
and an `app.css` entry. The `--pv-*` runtime CSS custom properties SHALL remain the canonical theming
API; shadcn design tokens and Tailwind `@theme` values SHALL alias `var(--pv-*)` so a single runtime
override reskins primitives, shell, and utilities with no rebuild.

#### Scenario: One reskin knob drives all layers

- **WHEN** a `--pv-*` color token is overridden at `:root` at runtime
- **THEN** shadcn primitives, Tailwind utilities that reference it, and the shell all restyle with no rebuild

#### Scenario: Owned, ejectable primitives

- **WHEN** a developer customizes a shadcn-vue component
- **THEN** they edit its source under `apps/spa/components/ui/` without forking the whole theme

### Requirement: Vue-native state

Shared client state SHALL use Vue reactivity (and Pinia where cross-page state is required). The theme
MUST NOT depend on `@preact/signals-core`, `nanostores`, `@nanostores/query`, or `@lit/context`.

#### Scenario: No cross-framework state stack

- **WHEN** the theme's dependency graph is inspected
- **THEN** it contains no `lit`, `@lit/context`, `@preact/signals-core`, `nanostores`, or Web Awesome packages

## MODIFIED Requirements

### Requirement: PvApp outer container

The theme floor SHALL provide `PvApp.vue`, an outer container used as the
Inertia persistent layout so it wraps every page and survives navigations.
`PvApp` SHALL provide the extension registry and current page props to
descendants, host Nuxt UI's `UApp` and resolved `UTheme` providers and overlay
context above the layout, and render `PvLayout` with page content in its main
region. It MUST NOT be a web component.

#### Scenario: Persistent wrapper survives navigation

- **WHEN** the user navigates between Inertia pages under the Skylight theme
- **THEN** `PvApp`, `UApp`, `UTheme`, extension context, and overlay providers
  persist while page content swaps

#### Scenario: Provides registry and normalized theme context

- **WHEN** a descendant injects the extension registry or a new generic control
  renders during the first page render
- **THEN** it receives the extension context and synchronously resolved UTheme
  configuration without prop-drilling or a second render

### Requirement: Nuxt UI primitives and runtime theme bridge

The theme SHALL use Nuxt UI as its generic Vue component library while
remaining a Vue, Vite, Inertia application and MUST NOT introduce the Nuxt
framework. New and refactored generic buttons, inputs, selects, tables,
dialogs, drawers, tabs, menus, notifications, and similar controls SHALL use
Nuxt UI directly. Existing pre-contract controls MAY remain until their named
migration slice. phpVMS MUST NOT create `PvButton`, `PvInput`, or similar
wrappers solely for styling. The persistent `PvApp` SHALL host `UApp` and the
resolved `UTheme`. The `--pv-*` runtime CSS custom properties SHALL remain the
canonical phpVMS domain theming API; Nuxt UI tokens SHALL be supplied by the
generated runtime theme stylesheet and resolved `UTheme` settings so a saved
theme reskins generic controls and domain components without a frontend
rebuild.

#### Scenario: Nuxt UI generic control renders under the persistent app

- **WHEN** an Inertia page renders a new or refactored generic control below
  `PvApp`
- **THEN** it uses a Nuxt UI component directly and receives the initial
  resolved `UTheme` configuration without a phpVMS generic-control wrapper

#### Scenario: One reskin source drives both ownership layers

- **WHEN** a published runtime theme changes a `--pv-*` domain token and a
  curated Nuxt UI component setting
- **THEN** domain components use the changed CSS variable and generic Nuxt UI
  controls use the matching resolved setting without rebuilding the frontend

#### Scenario: No Nuxt framework is introduced

- **WHEN** the pilot frontend runtime and build configuration are inspected
- **THEN** they retain Vue, Vite, and Inertia and use Nuxt UI only as a Vue
  component library

## ADDED Requirements

### Requirement: First generic-control conversion preserves the toolbar

The first Nuxt UI control conversion SHALL replace the dashboard toolbar's Add
widget, Reset, and Customize/Done buttons with Nuxt UI buttons directly. It
MUST preserve their accessible names, events, disabled behavior, menu behavior,
current 32px visual geometry, and documented dashboard `.pv-*` hook. It MUST
NOT convert unrelated generic control families in this slice.

#### Scenario: Dashboard customization contract remains intact

- **WHEN** a pilot selects Customize, opens Add widget, and selects Done
- **THEN** the existing toolbar behavior, accessible names, and visible editing
  state remain intact while the three toolbar controls are Nuxt UI buttons

#### Scenario: Reset and disabled Add widget remain intact

- **WHEN** editing is active and either Reset is selected or no widgets remain
  available to add
- **THEN** Reset emits its existing event and Add widget retains its disabled
  behavior

#### Scenario: Toolbar visual contract remains intact

- **WHEN** the toolbar renders on desktop and mobile
- **THEN** the converted controls retain 32px geometry and the documented
  dashboard `.pv-*` custom-CSS hook

### Requirement: Nuxt UI preserves the custom production build contract

The Nuxt UI integration SHALL retain the theme-specific Vite manifest,
externalized Vue host build, shared Vue vendor module, addon build, and browser
import map. Production host and addon code MUST resolve one Vue runtime. The
Nuxt UI Vite integration SHALL be configured for Inertia without replacing the
existing persistent layout or page resolver.

#### Scenario: Production host and addon share Vue

- **WHEN** the Skylight production assets and a pre-built addon are loaded
- **THEN** the host, Nuxt UI providers, and addon resolve one Vue runtime and
  the addon renders under the existing import-map contract

#### Scenario: Inertia navigation preserves providers

- **WHEN** the pilot navigates between Inertia pages after Nuxt UI is installed
- **THEN** `PvApp`, `UApp`, `UTheme`, extension context, and overlay providers
  remain mounted while page content changes

## RENAMED Requirements

- FROM: `### Requirement: shadcn-vue primitives and Tailwind with token bridge`
- TO: `### Requirement: Nuxt UI primitives and runtime theme bridge`

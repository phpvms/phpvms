## ADDED Requirements

### Requirement: Versioned phpVMS theme document and transient raw import

phpVMS SHALL own a versioned public runtime-theme document. It SHALL accept a
valid phpVMS document or raw JSON exported by nuxt-ui-themes.com. The importer
MUST normalize raw builder JSON into `nuxtUi.theme`, provide versioned defaults
for `nuxtUi.components` and `phpvms`, and produce version 1 as the first phpVMS
theme document version. Import and preview MUST remain transient and MUST NOT
create a stored draft or change the active publication. Only publish creates
durable revision data. Every document version other than version 1 MUST be
rejected until a later schema explicitly defines its migration from version 1.

#### Scenario: Raw Theme Builder import

- **WHEN** a valid raw Nuxt UI Theme Builder export is submitted for transient
  import
- **THEN** phpVMS returns a valid normalized versioned document with the raw
  theme under `nuxtUi.theme` and default phpVMS/component settings without
  persisting it or changing the active publication

#### Scenario: Unsupported document version

- **WHEN** phpVMS receives any document version other than version 1
- **THEN** validation rejects it with a version error and leaves the active
  publication unchanged

### Requirement: Safe curated component configuration

The public theme document SHALL expose only curated identifiers and validated
values for Nuxt UI component defaults. It MUST reject arbitrary Tailwind class
strings and unrestricted Nuxt UI slot-class objects. Continuous values SHALL
be represented by CSS variables, while finite choices SHALL be represented by
validated identifiers resolved by compiled source mappings.

#### Scenario: Curated settings resolve

- **WHEN** a valid document selects a pill button shape and compact density
- **THEN** the typed frontend resolver produces the corresponding compiled
  `UTheme` `props` and `ui` output without putting class strings in the public
  document

#### Scenario: Unsafe runtime class input

- **WHEN** imported JSON contains an arbitrary Tailwind class or unrestricted
  slot-class object in a phpVMS component setting
- **THEN** validation rejects the import and leaves the active publication
  unchanged

### Requirement: Globally renderer-scoped published revisions

phpVMS SHALL persist immutable published-theme revisions globally scoped by
rendered theme name. Each revision SHALL contain the schema version, normalized
document, optional custom CSS, content-derived revision, and publication time.
Publishing MUST validate and normalize before writing a browser-ready
`theme.css` and any `custom.css` to revisioned asset storage, then serialize and
atomically change the active database pointer. A later tenant or airline scope
MUST require an explicit schema change.

#### Scenario: Successful publication

- **WHEN** a valid normalized theme is published for the active renderer
- **THEN** the active pointer references one complete immutable revision whose
  assets contain the required `:root` and `.dark` `--ui-*` and `--pv-*` values

#### Scenario: Asset write failure

- **WHEN** a required runtime stylesheet cannot be written
- **THEN** the active revision remains unchanged and no incomplete revision is
  served

#### Scenario: Activation failure or competing publication

- **WHEN** active-pointer persistence fails or two publications compete
- **THEN** readers continue to resolve one complete old or new revision and
  never a mixed or incomplete revision

#### Scenario: Roll back publication

- **WHEN** a prior complete revision is selected through the publication
  service for rollback
- **THEN** phpVMS atomically makes that revision active without rewriting its
  immutable assets

### Requirement: Public runtime theme asset delivery

Runtime theme assets SHALL be published through a dedicated configurable asset
disk and SHALL have anonymously readable immutable HTTP URLs. The local default
SHALL use public storage. A private remote disk MUST use an unauthenticated
application delivery route with immutable cache headers instead of exposing a
private object URL in a stylesheet link.

#### Scenario: Anonymous stylesheet fetch

- **WHEN** an unauthenticated browser requests the active `theme.css` and any
  active `custom.css`
- **THEN** both URLs return the exact active revision content with immutable
  cache headers and no authentication challenge

### Requirement: Pre-paint stylesheets and first-render component theme

The Skylight document shell SHALL load application CSS, generated `theme.css`,
and optional `custom.css` in that order before application JavaScript in both
production and Vite development. It MUST apply the saved light, dark, or auto
mode before those stylesheets paint. The initial Inertia response SHALL include
the validated normalized theme document, which the typed frontend resolver
MUST synchronously map to `UTheme` `props` and `ui` before the first Vue render.
The Inertia asset version SHALL change when either the frontend manifest or
active theme revision changes.

#### Scenario: Hard reload uses saved palette

- **WHEN** a pilot hard-reloads a page with a published dark theme
- **THEN** the document has the saved dark mode and generated palette before
  first paint, and the first Vue render uses matching resolved UTheme data

#### Scenario: Auto mode follows the browser preference

- **WHEN** the saved mode is auto and the browser prefers dark mode
- **THEN** the document applies dark mode before first paint and retains auto as
  the saved preference

#### Scenario: No published revision

- **WHEN** no published runtime-theme revision exists
- **THEN** the shell loads bundled fallback tokens and renders the application
  without suppressing or delaying the normal page

#### Scenario: Vite development stylesheet order

- **WHEN** the Skylight frontend runs from the Vite development server
- **THEN** application CSS is a blocking stylesheet before application
  JavaScript and any active runtime theme and custom styles follow it in order

#### Scenario: Published revision refreshes an open client

- **WHEN** a new runtime-theme revision is published and an open client performs
  a subsequent Inertia visit
- **THEN** the changed asset version forces a document reload that uses the new
  stylesheet URLs and matching normalized theme document

### Requirement: Stable phpVMS domain theming API

phpVMS SHALL preserve `--pv-*` variables and meaningful `.pv-*` selectors as
the public theming API for phpVMS domain components. It MUST document supported
variables and hooks, keep structural component CSS in the application bundle,
and load custom CSS last. Generic Nuxt UI internals MUST NOT be a public
customization API.

#### Scenario: Documented custom CSS hook

- **WHEN** a published custom stylesheet overrides a documented `.pv-*` hook
- **THEN** it overrides the corresponding phpVMS domain element without
  requiring a frontend rebuild

#### Scenario: Domain component ejection

- **WHEN** an airline replaces one focused domain component in source
- **THEN** it can preserve the documented component props, events, slots, and
  required style import without copying an entire route or shell

### Requirement: External builder integration boundary

The core SHALL document the phpVMS theme import/export schema and preview
payload contract for themes.phpvms.net. It SHALL provide the normalized
document and preview CSS needed to render representative generic and domain
components. This change MUST NOT implement, host, persist drafts for, or deploy
the external builder.

#### Scenario: Builder preview integration

- **WHEN** an external builder submits valid raw Theme Builder JSON for preview
- **THEN** the core contract returns normalized phpVMS theme data and preview
  output without persisting a draft or requiring builder implementation in
  phpVMS

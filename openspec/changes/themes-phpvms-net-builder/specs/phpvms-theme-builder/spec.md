## ADDED Requirements

### Requirement: Standalone builder ownership

themes.phpvms.net SHALL be a standalone application with its own repository and
OpenSpec root. It SHALL consume the phpVMS runtime theme schema and preview
contract, but it MUST NOT implement phpVMS persistence, generated stylesheet
publication, or pilot-frontend asset loading.

#### Scenario: Builder output is handed to core

- **WHEN** an author exports a completed theme from themes.phpvms.net
- **THEN** the builder produces the phpVMS versioned document for core
  validation and publication without writing phpVMS runtime storage directly

### Requirement: Upstream Theme Builder adaptation

The builder SHALL preserve upstream Nuxt UI Theme Builder editing for semantic
colors, neutral palette, fonts, radius, light/dark configuration, and Nuxt UI
token overrides. It SHALL map that state into the phpVMS versioned document
rather than exporting upstream state as the phpVMS public contract.

#### Scenario: Upstream raw import

- **WHEN** an author imports valid raw Nuxt UI Theme Builder JSON
- **THEN** the editor restores the upstream editing state and presents it as
  `nuxtUi.theme` in a phpVMS versioned draft

### Requirement: Curated phpVMS authoring controls

The builder SHALL provide controls for curated Nuxt UI component settings,
density, navigation, table settings, domain-component tokens, and custom CSS.
It MUST export stable schema identifiers and MUST NOT provide arbitrary
Tailwind-class strings or unrestricted Nuxt UI slot-class object editing as
normal theme settings.

#### Scenario: Curated button setting

- **WHEN** an author selects the pill button shape
- **THEN** the exported document contains the schema identifier for that shape
  and does not contain an arbitrary compiled class string

### Requirement: Real component preview

The builder SHALL preview real Nuxt UI generic controls and real phpVMS
pilot-frontend domain components using the normalized document, generated
preview CSS, and version-compatible frontend resolver contract supplied by
phpVMS. It SHALL resolve `UTheme` data in the frontend, support light and dark
preview modes, and keep unsaved edits in builder memory until exported or
submitted to phpVMS.

#### Scenario: Unsaved dark preview

- **WHEN** an author changes palette values and enables dark preview
- **THEN** the builder displays the changed real generic and domain components
  without publishing a runtime theme revision

### Requirement: Strict versioned export

The builder SHALL import both raw upstream JSON and supported phpVMS document
versions, show validation/migration errors, apply only schema-defined defaults,
and export the current phpVMS versioned document. phpVMS core MUST remain the
authoritative validator for publication.

#### Scenario: Invalid imported document

- **WHEN** an imported document includes an unsupported component setting
- **THEN** the builder identifies the invalid field and prevents export until
  the document is migrated or corrected

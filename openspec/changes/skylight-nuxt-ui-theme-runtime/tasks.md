## 1. Contract and migration preparation

- [ ] 1.1 Capture a current raw nuxt-ui-themes.com export fixture and define the supported-field allowlist, version-1 phpVMS document schema, defaults, supported migrations, and unknown-version rejection.
- [ ] 1.2 Document the supported `--pv-*` variables and stable `.pv-*` hooks, including the dashboard toolbar hook and the source-level domain-component ejection contract.
- [ ] 1.3 Define the core import/export and transient preview payload contract for themes.phpvms.net without adding builder implementation, draft storage, hosting, or deployment work to this repository.
- [ ] 1.4 Define the authorized admin placement for transient import/preview, publish, revision history, and rollback before adding the backend UI or routes.

## 2. Backend theme publication

- [ ] 2.1 Add immutable published-theme revision storage and an active pointer globally scoped by rendered theme name, with schema version, normalized JSON, optional custom CSS, content-derived revision, and publication timestamp.
- [ ] 2.2 Implement phpVMS-document validation and transient raw Theme Builder normalization/preview with defaults, supported migrations, field-level errors, unknown-version rejection, unsafe-class rejection, and no draft persistence.
- [ ] 2.3 Implement the CSS renderer for complete `:root` and `.dark` `--ui-*` and `--pv-*` declarations, keeping layouts, Tailwind directives, and Vue component styles out of generated output.
- [ ] 2.4 Add a dedicated configurable theme-asset disk that produces anonymously readable immutable URLs, with a public local default and an unauthenticated cached delivery route for private remote storage.
- [ ] 2.5 Implement serialized publication that writes required assets before atomically changing the active pointer, preserves one complete active revision on write/activation/concurrency failure, and supports rollback to an immutable prior revision.
- [ ] 2.6 Add the authorized transient import/preview, publish, revision history, and rollback paths using the validated service boundary.
- [ ] 2.7 Share the active validated normalized document in initial Inertia props and include the active publication revision in Inertia asset versioning.

## 3. Nuxt UI frontend foundation

- [ ] 3.1 Add and pin the verified Nuxt UI packages, configure the Nuxt UI Vite plugin for Inertia, and install the Vue plugin while retaining the custom manifest, externalized Vue host build, shared vendor module, addon build, page resolver, and persistent layout without adding the Nuxt framework.
- [ ] 3.2 Replace shadcn-specific application CSS bridge/configuration with Nuxt UI CSS and provider setup; host `UApp` and resolved `UTheme` below the persistent `PvApp`.
- [ ] 3.3 Implement typed frontend validation/defaults and exhaustive compiled mappings that synchronously resolve normalized curated settings into `UTheme` `props` and `ui`.
- [ ] 3.4 Consume the normalized Inertia theme document and resolve it before the first Vue render; keep continuous runtime values in generated CSS variables.
- [ ] 3.5 Audit each shadcn-era dependency, configuration file, and local generic primitive import before removing only unused shadcn-specific source and dependencies; retain active unrelated dependencies such as `PvIcon` requirements.

## 4. Pre-paint stylesheet delivery

- [ ] 4.1 Refactor the Skylight Blade asset assembly to emit application CSS, revisioned theme CSS, and optional revisioned custom CSS as separate blocking links in that order before module scripts in production.
- [ ] 4.2 Add the equivalent blocking application stylesheet path for Vite development mode, followed by active runtime theme/custom styles, without relying on the JavaScript CSS import for first paint.
- [ ] 4.3 Apply the saved light, dark, or auto mode synchronously before stylesheet paint while preserving auto as a preference and following the browser color-scheme setting.
- [ ] 4.4 Provide a safe fallback when no published theme exists, using bundled default tokens without suppressing the normal application render.

## 5. Representative generic-control slice

- [ ] 5.1 Convert only the dashboard toolbar Add widget, Reset, and Customize/Done controls to direct Nuxt UI buttons; do not introduce phpVMS control wrappers or convert unrelated controls.
- [ ] 5.2 Preserve toolbar accessible names, emits, disabled Add-widget state, menu behavior, stable `.pv-*` hook, and current 32px visual geometry with application CSS and documented variables.
- [ ] 5.3 Keep dashboard, profile, PIREP, shell, and focused domain components structurally unchanged outside the requested toolbar conversion.

## 6. Automated verification

- [ ] 6.1 Add backend unit and feature coverage for raw JSON import, no draft persistence, schema migrations, unknown-version and unsafe-class rejection, light/dark CSS rendering, write/activation/concurrency atomicity, rollback, anonymous asset fetches, immutable cache headers, revision invalidation, Blade link order, and fallback output.
- [ ] 6.2 Add frontend type and unit coverage for schema defaults/migrations, exhaustive resolver mappings, normalized Inertia input, first-render `UTheme` props/ui, and dashboard-toolbar events, names, disabled behavior, hooks, and geometry.
- [ ] 6.3 Run the frontend typecheck, frontend unit suite, production frontend build, existing shared-Vue artifact test, and relevant PHP feature tests; record their actual results.
- [ ] 6.4 Run a raw Theme Builder JSON import and verify transient preview, no stored draft, and published light/dark output with complete runtime variables and resolved Nuxt UI configuration.
- [ ] 6.5 Run the existing addon extensibility browser test against production-built assets and verify the host, Nuxt UI providers, and addon use one shared Vue runtime.
- [ ] 6.6 With a browser client open, publish a new revision, perform an Inertia visit, and verify the forced document reload, new stylesheet URLs, and matching normalized document/UTheme output.
- [ ] 6.7 Use desktop and mobile browser checks to verify hard-reload no-flash behavior for light/dark/auto, development stylesheet order, one documented `.pv-*` custom-CSS override, and the complete converted toolbar flow.
- [ ] 6.8 Browser-check the existing dashboard, profile, and PIREP views after the migration and record visual-regression evidence before accepting the change.

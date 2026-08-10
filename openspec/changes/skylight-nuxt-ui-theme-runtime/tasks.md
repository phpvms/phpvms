## 1. Contract and runtime preparation

- [x] 1.1 Capture a current raw nuxt-ui-themes.com export fixture and define the supported-field allowlist, version-1 phpVMS document schema, defaults, first-version boundary, and unsupported-version rejection.
- [x] 1.2 Document the supported `--pv-*` variables and stable `.pv-*` hooks, including the dashboard toolbar hook and the source-level domain-component ejection contract.
- [x] 1.3 Define the core import/export and transient preview payload contract for themes.phpvms.net without adding builder implementation, draft storage, hosting, or deployment work to this repository.
- [x] 1.4 Record the future Config > Branding placement after Settings with `view:branding` and `edit:branding`, while deferring the editor mechanics and admin routes to a later OpenSpec change.

## 2. Backend theme publication

- [x] 2.1 Add immutable published-theme revision storage and an active pointer globally scoped by rendered theme name, with schema version, normalized JSON, optional custom CSS, content-derived revision, and publication timestamp.
- [x] 2.2 Implement phpVMS-document validation and transient raw Theme Builder normalization/preview with defaults, field-level errors, unsupported-version rejection, unsafe-class rejection, and no draft persistence.
- [x] 2.3 Implement the CSS renderer for complete `:root` and `.dark` `--ui-*` and `--pv-*` declarations, keeping layouts, Tailwind directives, and Vue component styles out of generated output.
- [x] 2.4 Add a dedicated configurable theme-asset disk that produces anonymously readable immutable URLs, with a public local default and an unauthenticated cached delivery route for private remote storage.
- [x] 2.5 Implement serialized publication that writes required assets before atomically changing the active pointer, preserves one complete active revision on write/activation/concurrency failure, and supports rollback to an immutable prior revision.
- [x] 2.6 Share the active validated normalized document in initial Inertia props and include the active publication revision in Inertia asset versioning.

## 3. Nuxt UI frontend foundation

- [x] 3.1 Add and pin the verified Nuxt UI packages, configure the Nuxt UI Vite plugin for Inertia, and install the Vue plugin while retaining the custom manifest, externalized Vue host build, shared vendor module, addon build, page resolver, and persistent layout without adding the Nuxt framework.
- [x] 3.2 Replace shadcn-specific application CSS bridge/configuration with Nuxt UI CSS and provider setup; host `UApp` and resolved `UTheme` below the persistent `PvApp`.
- [x] 3.3 Implement typed frontend validation/defaults and exhaustive compiled mappings that synchronously resolve normalized curated settings into `UTheme` `props` and `ui`.
- [x] 3.4 Consume the normalized Inertia theme document and resolve it before the first Vue render; keep continuous runtime values in generated CSS variables.
- [x] 3.5 Audit each shadcn-era dependency, configuration file, and local generic primitive import before removing only unused shadcn-specific source and dependencies; retain active unrelated dependencies such as `PvIcon` requirements.

## 4. Pre-paint stylesheet delivery

- [x] 4.1 Refactor the Skylight Blade asset assembly to emit application CSS, revisioned theme CSS, and optional revisioned custom CSS as separate blocking links in that order before module scripts in production.
- [x] 4.2 Add the equivalent blocking application stylesheet path for Vite development mode, followed by active runtime theme/custom styles, without relying on the JavaScript CSS import for first paint.
- [x] 4.3 Apply the saved light, dark, or auto mode synchronously before stylesheet paint while preserving auto as a preference and following the browser color-scheme setting.
- [x] 4.4 Provide a safe fallback when no published theme exists, using bundled default tokens without suppressing the normal application render.

## 5. Representative generic-control slice

- [x] 5.1 Convert only the dashboard toolbar Add widget, Reset, and Customize/Done controls to direct Nuxt UI buttons; do not introduce phpVMS control wrappers or convert unrelated controls.
- [x] 5.2 Preserve toolbar accessible names, emits, disabled Add-widget state, menu behavior, stable `.pv-*` hook, and current 32px visual geometry with application CSS and documented variables.
- [x] 5.3 Keep dashboard, profile, PIREP, shell, and focused domain components structurally unchanged outside the requested toolbar conversion.

## 6. Automated verification

- [x] 6.1 Add backend unit and feature coverage for raw JSON import, no draft persistence, first-version and unsupported-version behavior, unsafe-class rejection, light/dark CSS rendering, write/activation/concurrency atomicity, rollback, anonymous asset fetches, immutable cache headers, revision invalidation, Blade link order, and fallback output.
- [x] 6.2 Add frontend type and unit coverage for schema defaults, first-version and unsupported-version behavior, exhaustive resolver mappings, normalized Inertia input, first-render `UTheme` props/ui, and dashboard-toolbar events, names, disabled behavior, hooks, and geometry.
- [x] 6.3 Run the frontend typecheck, frontend unit suite, production frontend build, existing shared-Vue artifact test, and relevant PHP feature tests; record their actual results.
- [x] 6.4 Run a raw Theme Builder JSON import and verify transient preview, no stored draft, and published light/dark output with complete runtime variables and resolved Nuxt UI configuration.
- [x] 6.5 Run the existing addon extensibility browser test against production-built assets and verify the host, Nuxt UI providers, and addon use one shared Vue runtime.
- [x] 6.6 With a browser client open, publish a new revision, perform an Inertia visit, and verify the forced document reload, new stylesheet URLs, and matching normalized document/UTheme output.
- [x] 6.7 Use desktop and mobile browser checks to verify hard-reload no-flash behavior for light/dark/auto, development stylesheet order, one documented `.pv-*` custom-CSS override, and the complete converted toolbar flow.
- [x] 6.8 Browser-check the existing dashboard, profile, and PIREP views after the runtime-theme integration and record visual-regression evidence before accepting the change.

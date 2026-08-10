## Why

The completed Vue-only floor selected shadcn-vue, but the pilot frontend now has
an explicit architecture contract for Nuxt UI and a backend-owned runtime theme
format. The current shell only loads Vite output, so a saved palette cannot be
available before Vue mounts and there is no supported published-theme path.

## What Changes

1. Replace the shadcn-vue generic-control floor with Nuxt UI while retaining
   Vue, Vite, Inertia, the persistent `PvApp`, and Vue-based extension seams.
   This does not add the Nuxt framework.
2. Add Nuxt UI as the generic control library. New and refactored buttons,
   inputs, selects, tables, dialogs, drawers, tabs, menus, notifications, and
   similar controls use Nuxt UI directly; phpVMS does not add `PvButton`,
   `PvInput`, or styling wrappers for them. Existing controls move in named
   migration slices.
3. Add the phpVMS-owned, versioned public theme document. It imports raw JSON
   exported by nuxt-ui-themes.com, normalizes it into the phpVMS document, and
   supplies defaults for phpVMS and curated component settings.
4. Resolve validated component settings into `UTheme` `props` and `ui` values.
   Continuous values become CSS variables; finite choices map to compiled
   source values. Runtime theme JSON cannot contain arbitrary Tailwind classes
   or unrestricted Nuxt UI slot-class objects.
5. Persist immutable, globally renderer-scoped theme revisions through the
   backend. Import and preview remain transient. Publishing generates
   browser-ready `theme.css` with `--ui-*` and `--pv-*` values, publishes it
   atomically at anonymously readable cache-versioned URLs, and shares the
   normalized document with the initial Inertia response for synchronous
   frontend resolution.
6. Load application CSS, generated `theme.css`, and optional `custom.css` in
   that order before first paint. Apply the saved light/dark palette before the
   page paints.
7. Preserve `--pv-*` variables and stable `.pv-*` hooks as the phpVMS domain
   component and advanced-custom-CSS APIs. Keep domain components focused and
   independently ejectable.
8. Convert the dashboard toolbar's existing Add widget, Reset, and
   Customize/Done controls as the first Nuxt UI slice, preserving their current
   appearance and browser interaction contract.
9. Remove shadcn-specific source, configuration, and dependencies only after
   each import is verified. Retain unrelated active dependencies and existing
   domain components.

## Capabilities

### New Capabilities

1. `nuxt-ui-theme-runtime`: The versioned phpVMS theme document, raw Nuxt UI
   Theme Builder import normalization, curated resolver, backend publication,
   pre-paint stylesheets, cache versioning, and public customization contract.

### Modified Capabilities

1. `vue-theme-floor`: Replace its shadcn-vue primitive requirement with Nuxt UI
   while retaining the Vue-only, Vite, Inertia, persistent-app, and Vue
   extension requirements.

## Impact

1. Frontend: `resources/js/apps/fe-vue` package, Vite and Vue bootstrap,
   `PvApp`, application CSS, generic-control use sites, component tests, and
   browser tests.
2. Backend: a published-theme model and migration, normalization and CSS
   generation services, publication storage, theme administration/import
   endpoints, `HandleInertiaRequests`, and
   `resources/views/layouts/skylight/spa.blade.php`.
3. Public contract: phpVMS defines the versioned JSON schema, `--pv-*` tokens,
   and `.pv-*` hooks. Nuxt UI internals and generated markup are not public
   theme APIs.
4. External builder: themes.phpvms.net may consume and emit the defined JSON
   and preview contract, but its builder implementation is out of scope for
   this repository and change.

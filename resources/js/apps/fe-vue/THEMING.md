# Pilot frontend theming contract

## Status

1. This is the target architecture for the ongoing pilot-frontend migration.
2. Existing components may still predate this contract.
3. New and refactored frontend work must follow this contract.

## Purpose

This document defines the public theming and component-extension contract for the phpVMS pilot frontend. A contributor should be able to add or restyle a component without coupling an airline theme to phpVMS implementation details or Nuxt UI internals.

## Core decisions

1. The target pilot frontend uses Vue, Vite, Inertia, Tailwind CSS, and Nuxt UI. It does not use the Nuxt framework.
2. Nuxt UI owns generic interface components.
3. phpVMS owns the public theme format, domain components, runtime CSS variables, and stable CSS hooks.
4. Runtime theme changes must not require rebuilding the frontend.
5. Source-level component ejection is available for changes that cannot be expressed through the runtime theme system.
6. An airline theme must survive reasonable changes to the underlying component library. Nuxt UI configuration is an implementation detail, not the phpVMS public API.

## Theme builder

1. phpVMS will host a fork of the MIT-licensed [Nuxt UI Theme Builder](https://github.com/mattycraig/nuxt-theme-builder) at `themes.phpvms.net`.
2. The fork keeps the upstream editor for semantic colors, neutral palettes, fonts, radius, light mode, dark mode, and Nuxt UI token overrides.
3. The fork adds phpVMS-specific controls for component defaults, density, navigation, tables, domain-component tokens, and real pilot-frontend previews.
4. Theme authors should use the builder as the normal customization path.
5. The builder must preview real Nuxt UI components and real phpVMS domain components.
6. The builder must not expose arbitrary Tailwind class strings as normal theme settings.

## Customization hierarchy

| Priority | Mechanism                             | Intended use                                                                |
| -------- | ------------------------------------- | --------------------------------------------------------------------------- |
| 1        | Theme builder tokens                  | Colors, fonts, radius, light and dark palettes, spacing scales, and density |
| 2        | Theme builder component settings      | Nuxt UI component defaults and curated visual variants                      |
| 3        | phpVMS `--pv-*` variables             | phpVMS domain components and application-specific layout values             |
| 4        | Stable `.pv-*` hooks and `custom.css` | Targeted advanced overrides                                                 |
| 5        | Component ejection                    | Markup, behavior, or structure that must be replaced                        |

## Theme JSON

The phpVMS theme is versioned. It contains the upstream builder configuration without making that unversioned format the entire phpVMS contract.

```json
{
  "version": 1,
  "nuxtUi": {
    "theme": {
      "colors": {},
      "colorShades": {},
      "neutral": "zinc",
      "radius": 0.375,
      "font": "Inter",
      "lightOverrides": {},
      "darkOverrides": {},
      "darkColors": {},
      "darkColorShades": {},
      "darkNeutral": "zinc",
      "darkRadius": 0.375,
      "darkFont": "Inter"
    },
    "components": {
      "button": {
        "props": {
          "color": "primary",
          "variant": "solid",
          "size": "md"
        },
        "style": {
          "shape": "rounded",
          "weight": "medium"
        }
      },
      "input": {
        "props": {
          "color": "neutral",
          "size": "md"
        },
        "style": {
          "appearance": "underline"
        }
      }
    }
  },
  "phpvms": {
    "density": "compact",
    "navigation": "sidebar",
    "tableDensity": "compact"
  }
}
```

1. The example shows the intended ownership and nesting. The validated schema is authoritative.
2. The importer must accept raw JSON copied from the upstream Nuxt UI Theme Builder.
3. A raw upstream theme is normalized into `nuxtUi.theme`. Missing phpVMS and component settings receive defaults.
4. The normalized document is the complete versioned document an external builder may persist and export.
5. A theme schema change requires an explicit version and migration path.

## Builder integration contract

The core and `themes.phpvms.net` communicate through the versioned phpVMS
document. The builder is external to this repository. It owns its local drafts,
editor, hosting, and deployment.

The core preview service accepts a raw JSON string or a decoded JSON object:

```ts
type PreviewInput = string | Record<string, unknown>;

type PreviewResult = {
  document: PhpVmsThemeDocument;
  diagnostics: [];
  css: string;
  resolvedInput: {
    components: PhpVmsThemeDocument["nuxtUi"]["components"];
    phpvms: PhpVmsThemeDocument["phpvms"];
  };
  targets: {
    routes: ["dashboard", "profile", "pirep-detail"];
    components: ["button", "input", "dashboard-toolbar", "pirep-summary"];
  };
};
```

1. A raw Nuxt UI Theme Builder export is normalized into `nuxtUi.theme`. The
   core supplies versioned defaults for `nuxtUi.components` and `phpvms`.
2. `document` is the normalized version-1 phpVMS document. `diagnostics` is an
   empty list on success. `css` is the generated runtime stylesheet.
   `resolvedInput.components` and `resolvedInput.phpvms` are exactly the
   matching normalized document objects. They are not the internal resolved
   `UTheme` props or slot configuration.
3. `targets` contains stable preview identifiers. Its route identifiers are
   not Laravel route names or live IDs.
4. Preview is transient. It does not save a draft, write an asset, activate a
   publication, or define an HTTP route in this repository.
5. Invalid input throws Laravel `ValidationException`. Its field-error map has
   string field keys and arrays of message strings. Invalid JSON and a non-object
   use `$`; an unsupported document version uses `version`; schema failures use
   the schema property path.
6. The builder must submit only supported schema fields and curated identifiers.
   It must not send Tailwind classes, unrestricted Nuxt UI slot-class objects,
   or executable content as theme settings.

## Nuxt UI component settings

The builder is the primary way to change generic controls.

1. Theme-relevant default props may be configured globally. Examples include color, variant, size, orientation, and surface variant.
2. Content and application state are not theme settings. Labels, icons, loading state, disabled state, destinations, and submitted values remain component-instance data.
3. Curated visual choices such as `square`, `rounded`, `pill`, `boxed`, `underline`, `flat`, `bordered`, and `elevated` are stored as stable identifiers.
4. The frontend resolver maps those identifiers to class strings that already exist in the compiled source.
5. The resolved settings are passed to Nuxt UI through `UTheme` component props and UI slot overrides.
6. Do not store unrestricted Nuxt UI slot-class objects in the public theme JSON.
7. Do not wrap generic Nuxt UI components in phpVMS components solely for styling.

Conceptually:

```vue
<UTheme :props="resolvedTheme.props" :ui="resolvedTheme.ui">
  <App />
</UTheme>
```

## Runtime values and compiled choices

1. Continuous values use CSS custom properties. Examples include colors, radius, font family, font scale, control height, navigation height, and table row height.
2. Finite structural choices use validated identifiers and compiled mappings. Examples include input appearance, button shape, surface treatment, and density.
3. Arbitrary runtime Tailwind classes are prohibited. Tailwind cannot reliably compile classes that exist only in saved JSON.
4. Arbitrary CSS belongs in the advanced `custom.css` escape hatch.

Example compiled mapping:

```ts
const buttonShapes = {
  square: "rounded-none",
  rounded: "rounded-md",
  pill: "rounded-full",
} as const;
```

The JSON stores `pill`. It does not store `rounded-full`.

## CSS ownership

### Application and component CSS

1. Build-time CSS owns structure, responsive behavior, layout relationships, and default visual hierarchy.
2. Component styles should remain beside focused Vue components when they are specific to that component.
3. Global foundations and shared application utilities belong in the main application stylesheet.
4. Component styles belong in the `components` cascade layer.
5. Application CSS may contain fallback theme values so the frontend remains usable when a generated theme is unavailable.
6. A themeable value should be referenced through a semantic variable instead of repeated as a literal.

Example:

```css
@layer components {
  .pv-pirep-summary {
    display: flex;
    gap: 8px;
    padding: 12px;
    background: var(--pv-panel);
    color: var(--pv-ink);
  }
}
```

### Generated `theme.css`

1. Publishing a theme must generate `theme.css` through the backend.
2. It contains browser-ready `:root` and `.dark` declarations.
3. It defines the complete required `--ui-*` values and the phpVMS `--pv-*` values.
4. Application CSS statically emits every curated Tailwind palette variable that generated theme values may reference.
5. It may define runtime font faces or font variables when the selected font contract requires them.
6. It does not contain component layouts or copies of Vue component styles.
7. It does not contain Tailwind build directives such as `@theme` or source imports.
8. Publishing should replace the generated file atomically and change its cache version.

### Optional `custom.css`

1. `custom.css` is the unrestricted advanced escape hatch.
2. It loads after the application stylesheet and generated theme stylesheet.
3. Theme authors should target documented `.pv-*` hooks.
4. Theme authors should not depend on Nuxt UI generated markup, private data attributes, or internal class names.
5. Unlayered custom CSS loaded last must be able to override phpVMS component rules in the `components` layer.

## No-flash loading

Stylesheets are loaded in this order before the application is painted:

```html
<link rel="stylesheet" href="/build/skylight/assets/style-HASH.css" />
<link rel="stylesheet" href="/theme-assets/skylight/REVISION/theme.css" />
<link rel="stylesheet" href="/theme-assets/skylight/REVISION/custom.css" />
```

1. The first link contains global and component application CSS.
2. The generated theme stylesheet is the initial source of runtime token values.
3. The frontend must not wait until Vue mounts to apply the saved palette, font, radius, or phpVMS tokens.
4. The resolved `UTheme` object supplies component defaults during the first Vue render.
5. Live preview may inject unsaved values in memory. Published frontend pages use the generated stylesheet.

## phpVMS variables and hooks

1. `--pv-*` variables are a primary public theme API for phpVMS-owned components.
2. `.pv-*` classes are stable selector hooks for advanced custom CSS.
3. Stable hooks should identify meaningful application concepts, not every nested element.
4. Examples include `.pv-layout`, `.pv-navigation`, `.pv-region-header`, `.pv-region-main`, `.pv-dashboard`, `.pv-dashboard-toolbar`, `.pv-pirep-summary`, and `.pv-pirep-detail`.
5. Internal helper classes may remain private. They are not part of the compatibility contract.
6. A meaningful Nuxt UI control inside a domain workflow may receive a stable hook such as `.pv-pirep-accept-button`. This does not require a wrapper component.

### Supported variables

Published `theme.css` supplies the documented values below for `:root` and
`.dark`. Application CSS may provide fallback values when no theme has been
published. The legacy `--pv-color-*` aliases remain supported while components
use the semantic variables in the first two groups.

| Group                     | Variables                                                                                                                                                                                                                                                                                                                                             |
| ------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Surfaces and text         | `--pv-bg`, `--pv-panel`, `--pv-panel-inset`, `--pv-hover`, `--pv-line`, `--pv-line-strong`, `--pv-ink`, `--pv-ink-dim`, `--pv-ink-faint`, `--pv-track`                                                                                                                                                                                                |
| Brand and status          | `--pv-accent`, `--pv-accent-soft`, `--pv-cyan`, `--pv-green`, `--pv-amber`, `--pv-red`                                                                                                                                                                                                                                                                |
| Typography                | `--pv-font-body`, `--pv-font-mono`, `--pv-font-display`, `--pv-type-scale`                                                                                                                                                                                                                                                                            |
| Shape and elevation       | `--pv-radius-sm`, `--pv-radius-md`, `--pv-radius-lg`, `--pv-radius-xl`, `--pv-radius-full`, `--pv-shadow-panel`, `--pv-shadow-chrome`                                                                                                                                                                                                                 |
| Shell layout              | `--pv-nav-width`, `--pv-header-height`, `--pv-aside-width`, `--pv-container-width`                                                                                                                                                                                                                                                                    |
| Dashboard and diagnostics | `--pv-globe-sea`, `--pv-globe-land`, `--pv-globe-coast`, `--pv-slot-error-bg`, `--pv-slot-error-border`, `--pv-slot-error-text`                                                                                                                                                                                                                       |
| Legacy aliases            | `--pv-color-page`, `--pv-color-surface`, `--pv-color-surface-alt`, `--pv-color-surface-raised`, `--pv-color-border`, `--pv-color-text`, `--pv-color-text-muted`, `--pv-color-primary`, `--pv-color-primary-text`, `--pv-color-info`, `--pv-color-success`, `--pv-color-warning`, `--pv-color-error`, `--pv-font-family-base`, `--pv-font-family-mono` |
| Foundation shade scale    | `--pv-ink-50`, `--pv-ink-100`, `--pv-ink-150`, `--pv-ink-200`, `--pv-ink-300`, `--pv-ink-400`, `--pv-ink-500`, `--pv-ink-600`, `--pv-ink-700`, `--pv-ink-800`, `--pv-ink-850`, `--pv-ink-900`, `--pv-ink-950`                                                                                                                                         |

Themes should use the semantic variables above before depending on individual
foundation shades.

### Supported hooks

| Hook                    | Meaning                                                                                                 |
| ----------------------- | ------------------------------------------------------------------------------------------------------- |
| `.pv-layout`            | Persistent application shell                                                                            |
| `.pv-navigation`        | Navigation rail                                                                                         |
| `.pv-region-header`     | Application header region                                                                               |
| `.pv-region-main`       | Primary page region                                                                                     |
| `.pv-dashboard`         | Dashboard workspace                                                                                     |
| `.pv-dashboard-toolbar` | Dashboard title and Customize-mode toolbar, including Add widget, Reset, and Customize or Done controls |
| `.pv-dashboard-pilot`   | Dashboard pilot summary                                                                                 |
| `.pv-dashboard-board`   | Dashboard widget board                                                                                  |
| `.pv-profile`           | Pilot profile page                                                                                      |
| `.pv-pirep-detail`      | PIREP detail view                                                                                       |
| `.pv-pirep-summary`     | PIREP summary                                                                                           |
| `.pv-pirep-content`     | PIREP content region                                                                                    |
| `.pv-pirep-details`     | PIREP details sidebar                                                                                   |

Target these hooks from `custom.css`. Do not target Nuxt UI generated markup,
data attributes, or internal class names. For example:

```css
.pv-dashboard-toolbar {
  --pv-accent: #006db0;
}
```

## Domain components

1. phpVMS owns only aviation and airline-specific components.
2. Examples include flight cards, airport pairs, aircraft badges, route displays, PIREP summaries, flight progress, pilot statistics, and ACARS messages.
3. Domain components use the same `--ui-*` and `--pv-*` theme values as generic controls.
4. Domain components should expose focused, typed props and events.
5. Domain components should not hide unrelated data loading or page orchestration.
6. Generic controls inside domain components remain Nuxt UI components.

## Component ejection

1. Ejection is the source-level escape hatch for replacing markup, behavior, or structure.
2. Runtime theming does not require a rebuild. Ejecting and changing Vue source does require building a customized frontend distribution.
3. Domain components must remain focused enough that an airline can replace one component without copying a complete route or application shell.
4. Component contracts should use explicit props, events, and slots so an ejected replacement can preserve integration behavior.
5. Styles needed by an ejected component should be colocated with it or imported explicitly.
6. A later contributor guide must document the supported eject and build workflow once those commands exist.

### Source-level ejection contract

1. Ejection replaces one focused domain component in a customized frontend
   distribution. It is not a runtime theme feature and requires a frontend
   build.
2. A replacement preserves the original component's documented typed props,
   emitted events, slots, and accessibility contract at its integration point.
3. The replacement owns its markup and behavior. It imports or colocates the
   styles it requires and may use documented `--pv-*` variables and `.pv-*`
   hooks.
4. Generic controls inside the replacement use Nuxt UI directly. A replacement
   must not add phpVMS wrappers around generic controls only for styling.
5. The component remains focused. Replacing a dashboard widget, PIREP summary,
   route display, or similar domain component must not require copying the page
   route, persistent application shell, or unrelated domain components.

## Decision guide

| Desired change                                     | Correct mechanism                                                        |
| -------------------------------------------------- | ------------------------------------------------------------------------ |
| Change the primary palette                         | Theme builder semantic colors                                            |
| Make all buttons use the soft variant              | Theme builder Nuxt UI component defaults                                 |
| Make all buttons pill-shaped                       | Theme builder curated button style                                       |
| Change control height or table density             | Theme builder tokens and `--pv-*` variables                              |
| Change the PIREP header gap                        | A phpVMS spacing variable if globally themeable; otherwise component CSS |
| Restyle one documented PIREP element               | Stable `.pv-*` hook in `custom.css`                                      |
| Replace PIREP header markup                        | Eject the domain component                                               |
| Change an individual button label or loading state | Component-instance props, not the theme                                  |

## Compatibility rule

1. Airline themes depend on the versioned phpVMS JSON schema, documented `--pv-*` variables, and documented `.pv-*` hooks.
2. Airline themes do not depend on Nuxt UI internal markup or the internal shape of its application configuration.
3. If Nuxt UI changes or is replaced, phpVMS updates the resolver while preserving the public phpVMS contract or providing a versioned migration.

## Why

themes.phpvms.net needs a dedicated builder that lets theme authors safely
configure Nuxt UI and phpVMS pilot-frontend themes. Its editor, preview, and
deployment are a separate product from the phpVMS runtime and must not expand
the core implementation change.

## What Changes

1. Create the themes.phpvms.net builder in its own repository, starting from
   the MIT-licensed Nuxt UI Theme Builder fork.
2. Preserve the upstream editor for semantic colors, neutral palette, fonts,
   radius, light/dark values, and Nuxt UI token overrides.
3. Add phpVMS controls for curated generic-component settings, density,
   navigation, tables, domain-component tokens, and custom CSS.
4. Import raw Nuxt UI Theme Builder JSON and phpVMS versioned documents; export
   only the phpVMS versioned document defined by the core contract.
5. Preview real Nuxt UI controls and real phpVMS pilot-frontend domain
   components using the core preview contract.
6. Prohibit arbitrary Tailwind classes and unrestricted Nuxt UI slot-class
   configuration in normal builder controls.
7. Keep runtime theme persistence, CSS generation, authentication, production
   publication, and pilot-frontend loading in phpVMS core.

## Capabilities

### New Capabilities

1. `phpvms-theme-builder`: The standalone themes.phpvms.net editor, its
   phpVMS theme-document import/export, curated controls, and real-component
   preview integration.

### Modified Capabilities

1. None.

## Impact

1. A separate themes.phpvms.net repository, deployment, and OpenSpec root.
2. The phpVMS core change `skylight-nuxt-ui-theme-runtime` supplies the
   versioned JSON schema and preview contract consumed here.
3. Theme authors gain a visual builder; phpVMS core retains validation,
   normalization, publication, and runtime ownership.

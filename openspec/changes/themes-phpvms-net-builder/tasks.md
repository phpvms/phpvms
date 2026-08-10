## 1. Separate repository foundation

- [ ] 1.1 Create the themes.phpvms.net repository, initialize its own OpenSpec root, and move this change into that repository before implementation.
- [ ] 1.2 Fork the MIT-licensed Nuxt UI Theme Builder, retain required license and attribution material, and record the upstream release used as the baseline.
- [ ] 1.3 Establish the standalone application build, test, preview, deployment, and environment configuration without importing phpVMS runtime code or credentials.

## 2. phpVMS theme document adapter

- [ ] 2.1 Add typed draft state for the current phpVMS versioned theme document and the upstream builder state.
- [ ] 2.2 Implement raw upstream JSON and phpVMS-document import, schema-defined defaults, migration diagnostics, and field-level validation.
- [ ] 2.3 Implement versioned phpVMS document export with `nuxtUi.theme`, curated `nuxtUi.components`, and `phpvms` namespaces.
- [ ] 2.4 Add fixture-based round-trip tests against the exact current Theme Builder export and the phpVMS core normalizer contract.

## 3. Curated authoring experience

- [ ] 3.1 Preserve the upstream palette, neutral, font, radius, light/dark, and Nuxt UI token controls through the phpVMS adapter.
- [ ] 3.2 Add curated controls for generic-component settings, density, navigation, table settings, domain tokens, and the documented custom CSS field.
- [ ] 3.3 Enforce schema identifiers and reject arbitrary Tailwind classes and unrestricted Nuxt UI slot-class configuration from normal controls and imported drafts.
- [ ] 3.4 Add unit and interaction coverage for light/dark editing, curated component choices, invalid imports, and versioned export.

## 4. Real preview integration

- [ ] 4.1 Agree and version the core preview endpoint or component-package contract, including normalized documents, preview CSS, the frontend resolver contract, authentication, and allowed builder origins.
- [ ] 4.2 Render real Nuxt UI generic controls and the contract-provided phpVMS domain components by resolving the normalized document in the builder frontend.
- [ ] 4.3 Apply unsaved builder edits only in memory and verify that previewing cannot publish or mutate the phpVMS active theme.
- [ ] 4.4 Add desktop and mobile browser checks for light/dark preview, real components, responsive controls, and export/import round trips.

## 5. Release verification

- [ ] 5.1 Verify every exported builder document through phpVMS core validation, normalization, generated CSS output, and preview integration.
- [ ] 5.2 Verify the builder does not own phpVMS persistence, generated stylesheet publication, or runtime pilot-frontend asset loading.
- [ ] 5.3 Document the hand-off: authoring/import/export and preview belong to themes.phpvms.net; validation, publishing, pre-paint loading, and runtime resolution belong to phpVMS core.

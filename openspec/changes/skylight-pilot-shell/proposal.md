## Why

The persistent Vue shell does not yet carry the operational context required by the locked dispatch header. Its station comes from page-specific props, duty is static text, and the 390px reference header overflows.

## What Changes

1. Keep `PvApp`, Vue, Vite, Inertia, Nuxt UI, the shared Vue runtime, and the existing addon architecture.
2. Replace the current search-and-status header composition with the dispatch structure from `/html/body/header` of `mockups/e-dispatch.html`.
3. Add airline identity, active sector, duty state, METAR, UTC and local clocks, light/dark/auto control, and pilot account controls. Do not show fuel price or fuel-price movement.
4. Add typed shared Inertia props for pilot identity and operational chrome. Do not read persistent chrome from dashboard-only props.
5. Source the active sector and duty state from the pilot's active PIREP. Do not treat saved bids as active sectors.
6. Use the existing authenticated `/api/weather/{icao}` endpoint, `AirportService`, and configured `AviationWeather` provider. Extend the response only with observation metadata needed to identify stale data.
7. Define explicit desktop, tablet, and mobile information hierarchies with no horizontal overflow at 390px.
8. Preserve persistent Inertia navigation, runtime themes, addon slots, and no-flash theme loading.

## Capabilities

### New Capabilities

1. None.

### Modified Capabilities

1. `vue-theme-floor`: Add the persistent pilot dispatch header, shared operational props, responsive hierarchy, weather states, clocks, theme control, and account controls to the existing `PvApp` shell contract.

## Impact

1. Shared backend props: `app/Http/Middleware/HandleInertiaRequests.php` and focused typed DTOs under `app/Http/Data`.
2. Weather contract: `app/Http/Controllers/Frontend/WeatherController.php` and its feature tests. The provider remains `config('phpvms.metar_lookup')` through `AirportService`.
3. Persistent shell: `PvApp.vue`, `AppShell.vue`, `AppHeader.vue`, `HeaderStatus.vue`, `NavigationBrand.vue`, `useAppChrome.ts`, and focused clock, weather, sector, theme, and account components.
4. Runtime theme bootstrap: preserve the existing pre-paint mode script and stylesheet order in `resources/views/layouts/skylight/spa.blade.php`.
5. No new weather provider, frontend framework, Vue runtime, or generic-control wrapper.

## Context

`PvApp.vue` already persists across Inertia navigation and renders `AppShell.vue`, `AppNavigation.vue`, and `AppHeader.vue` inside `UApp` and `UTheme`. This structure must stay.

The current header contains search, a client UTC clock, a binary theme button, and pilot identity. `useAppChrome.ts` reads station from the active page's `currentAirport` prop. `PilotStatus.vue` always renders the translated on-duty label. Neither value is a persistent operational contract.

The existing weather path is `GET /api/weather/{icao}`. `WeatherController` calls `AirportService`, which uses the configured `App\Contracts\Metar` implementation. The current configured provider is `AviationWeather`. A successful response contains `icao`, raw `metar`, raw `taf`, conditions, temperature, wind, and units. Invalid ICAO returns 400. Provider failure or no provider data returns 503.

The locked desktop authority is `/html/body/header` in `mockups/e-dispatch.html`. At a 390px browser viewport, that reference has a 494px document width inside a 380px client width. The implementation must not copy that overflow.

## Goals / Non-Goals

**Goals:**

1. Add the full pilot dispatch header to the persistent `PvApp` shell.
2. Keep persistent chrome in shared Inertia props, separate from page DTOs.
3. Use the existing METAR provider and endpoint.
4. Define intentional desktop, tablet, and mobile hierarchies.
5. Preserve light, dark, auto, no-flash, addon, and single-Vue behavior.
6. Keep account and navigation actions inside Inertia where the destination is an Inertia page.

**Non-Goals:**

1. Do not show fuel price or fuel-price movement.
2. Do not replace Vue, Vite, Inertia, Nuxt UI, `PvApp`, or `AppShell`.
3. Do not use bids as an active-sector source. A pilot may hold multiple bids, and a bid is not an active flight.
4. Do not fetch weather during the Laravel page request.
5. Do not move dashboard career metrics into shared shell props.
6. Do not port the remaining Blade pilot pages in this change.

## Decisions

### D1. Preserve the persistent application boundary

Keep `PvApp.vue` as the persistent Inertia layout. Keep `UApp`, `UTheme`, the extension context, navigation, header slot, main slot, and toasts mounted around page changes. Recompose the header inside that boundary.

Generic buttons, menus, drawers, tooltips, and avatars use Nuxt UI directly. phpVMS header pieces remain focused domain components with documented `.pv-*` hooks.

### D2. Add typed shared pilot chrome

Expand the shared authenticated user and add a separate operational object:

| Shared prop                | Fields                                                      | Source                                                                 |
| -------------------------- | ----------------------------------------------------------- | ---------------------------------------------------------------------- |
| `auth.user`                | `id`, `name`, `avatar`, `ident`, `callsign`, `airline`      | authenticated `User`, its accessors, and `User.airline`                |
| `auth.user.airline`        | `name`, `icao`, `iata`, `logo`                              | authenticated pilot's `Airline` and `logo_url` accessor                |
| `pilotChrome.activeSector` | `pirepId`, `ident`, `departureIcao`, `arrivalIcao`, `state` | pilot's most recently updated PIREP in `IN_PROGRESS` or `PAUSED` state |
| `pilotChrome.duty`         | `state`, `label`, `color`                                   | active PIREP state mapping                                             |
| `pilotChrome.station`      | `icao`, `timezone`                                          | `User.current_airport`, then `User.home_airport`                       |

`pilotChrome` is null for an unauthenticated request. Its nested values are nullable when the source is missing.

The duty mapping is explicit: `IN_PROGRESS` is on duty, `PAUSED` is paused, and no active PIREP is off duty. If bad data contains more than one active PIREP, the most recently updated row wins deterministically.

This data is built in a focused typed DTO. `HandleInertiaRequests` shares it lazily for frontend Inertia responses. Dashboard, Profile, PIREP, and other page DTOs do not become shell inputs.

### D3. Keep airline identity useful without authentication

For an authenticated pilot, the header uses the pilot's airline name, ICAO, IATA, and logo. If the pilot or airline is absent, it uses the existing application name and default logo. Public Inertia pages therefore keep a stable brand without selecting an arbitrary airline.

### D4. Use the active PIREP as the active sector

An active sector exists only when the pilot has a PIREP in `IN_PROGRESS` or `PAUSED`. The header shows departure, arrival, and flight ident from that PIREP and links to its existing Inertia detail page.

With no active PIREP, the header shows `No active sector` and off-duty state. Saved bids remain visible in the bid workflow and do not enter persistent chrome.

### D5. Fetch METAR after the shell paints

The header weather composable reads `pilotChrome.station.icao` and fetches `/api/weather/{icao}` after mount. It cancels or ignores an old request when the station changes. It does not request weather when there is no station.

Keep the existing success fields and provider. Extend the success response with `observedAt` and `isStale`, derived from the parsed METAR observation time. Use 90 minutes as the stale boundary, matching the existing observation-age boundary in `App\Support\Metar`.

The UI states are distinct:

1. Loading: reserve the METAR region and show a short loading label or skeleton.
2. Missing station: show `No station` and make no request.
3. Missing METAR: a successful response with `metar: null` shows `No METAR`.
4. Stale: keep the raw METAR visible with a stale label and observation time.
5. Error: 400 or 503 shows `METAR unavailable` and a retry action. Do not show the provider exception text.
6. Loaded: show the raw METAR and its station in the full desktop or tablet status area.

The endpoint already uses the provider cache. The header must not add a second weather source.

### D6. Keep UTC and station-local clocks on the client

Keep the existing client UTC clock. Add a station-local clock using `Intl.DateTimeFormat` and `pilotChrome.station.timezone`. If the timezone is missing, show local time as unavailable instead of guessing an offset.

Update displayed clocks at minute boundaries. Use semantic time elements. Do not use a live region that announces every update.

### D7. Use one tri-state theme control

Replace the binary toggle UI with `light`, `dark`, and `auto`. Keep the `skylight.theme` storage key. In auto mode, follow system preference changes.

The pre-paint script in `resources/views/layouts/skylight/spa.blade.php` remains the first mode resolver. The Vue composable reads the same values and does not change the resolved class during hydration. Runtime `theme.css` and optional `custom.css` keep their existing order.

### D8. Add focused account controls

The pilot identity opens a Nuxt UI account menu. Authenticated actions include Profile and Sign out. Internal page navigation uses Inertia. The menu shows the pilot avatar fallback, name, ident, and callsign without duplicating page DTO data.

Unauthenticated public Inertia pages show a sign-in action instead of pilot identity.

### D9. Define three responsive header hierarchies

The desktop structure follows the dispatch reference but omits fuel:

1. Desktop at 1024px and wider uses one row: airline identity, active sector, duty state, METAR, UTC, local time, theme control, and pilot identity.
2. Tablet from 640px through 1023px uses two rows. The first row holds navigation, compact airline identity, theme, and account. The second row holds sector, duty, METAR, UTC, and local time.
3. Mobile below 640px uses one bounded row with navigation, airline mark, compact active sector, UTC, theme, and account triggers. A header-owned status drawer contains full duty, METAR, local time, and pilot identity.

All modes keep a keyboard-operable navigation trigger. Text may truncate only in the compact mobile summary when the full value is available in the status drawer. The document must not overflow horizontally at 390px.

### D10. Preserve addons and one Vue runtime

The shell remains in the host bundle. Nuxt UI providers and addon Vue components use the host's externalized Vue runtime. Header changes do not add a second app mount, provider tree, or Vue import-map entry.

## Risks / Trade-offs

1. Shared operational data adds a query to frontend Inertia responses. Mitigation: eager-load the small user relations and issue one indexed active-PIREP query only for authenticated requests.
2. Historical bad data can contain multiple active PIREPs. Mitigation: select the most recently updated row and cover the rule in a feature test.
3. Weather can be slow or absent. Mitigation: fetch after first paint, keep explicit states, and preserve page navigation when the request fails.
4. The full desktop contract is dense. Mitigation: use the defined two-row tablet layout and mobile status drawer rather than shrinking every field.
5. Theme state can flash or drift. Mitigation: keep one storage key and test the pre-paint script against the Vue mode resolver.

## Migration Plan

1. Add the typed shared props and their backend tests.
2. Extend the weather response with observation metadata and tests.
3. Add the clock, weather, sector, duty, theme, and account composables and components.
4. Recompose `AppHeader.vue` inside the existing persistent shell.
5. Add responsive styles and browser tests before enabling the header broadly.

Rollback restores the current header components and shared prop shape. `PvApp`, page DTOs, and the weather endpoint remain available.

## Open Questions

1. None for the shell proposal. The on-time metric decision remains in `pilot-summary-dashboard`.

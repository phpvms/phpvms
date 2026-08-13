## 1. Shared pilot chrome

- [x] 1.1 Add focused TypeScript-generating DTOs for airline identity, active sector, duty state, weather station, and pilot chrome
- [x] 1.2 Expand shared `auth.user` with ident, callsign, and airline identity without removing existing fields
- [x] 1.3 Build `pilotChrome` from the authenticated user's current or home airport and most recently updated in-progress or paused PIREP
- [x] 1.4 Map in-progress, paused, and no-active-PIREP states to explicit duty labels and semantic colors
- [x] 1.5 Share `pilotChrome` lazily from `HandleInertiaRequests` and return null for unauthenticated requests
- [x] 1.6 Regenerate TypeScript declarations and run the existing type-drift test

## 2. Shared-prop verification

- [x] 2.1 Add feature tests for authenticated and unauthenticated shared-prop shapes
- [x] 2.2 Add active-sector tests for in-progress, paused, none, saved-bids-only, and multiple-active-PIREP records
- [x] 2.3 Add station tests for current-airport, home-airport fallback, missing airport, and missing timezone
- [x] 2.4 Verify shared chrome does not read or require dashboard, profile, or PIREP page DTO props

## 3. METAR contract

- [x] 3.1 Extend the existing weather success response with parsed observation time and a 90-minute stale flag
- [x] 3.2 Keep the configured `AviationWeather` provider path through `AirportService`; do not add a provider or endpoint
- [x] 3.3 Add weather feature tests for current, stale, missing-METAR, invalid-ICAO, provider-error, and no-provider-data responses
- [x] 3.4 Ensure the client-facing error state does not display raw provider exception text

## 4. Shell state and composables

- [x] 4.1 Make `useAppChrome.ts` consume typed shared user and `pilotChrome` props only
- [x] 4.2 Add a METAR composable with loading, loaded, missing, stale, error, retry, station-change, and request-cancellation behavior
- [x] 4.3 Keep the UTC clock client driven and add a timezone-based local clock that updates at minute boundaries
- [x] 4.4 Replace the binary theme toggle state with light, dark, and auto using the existing storage key
- [x] 4.5 Synchronize auto mode with system preference changes without changing the saved auto value

## 5. Persistent dispatch header

- [x] 5.1 Recompose `AppHeader.vue` inside the existing `PvApp` and `AppShell` provider tree
- [x] 5.2 Add focused airline, active-sector, duty, METAR, clock, theme, and account header components using Nuxt UI for generic controls
- [x] 5.3 Link an active sector to the existing Inertia PIREP detail page and render a no-sector state otherwise
- [x] 5.4 Add authenticated Profile and Sign out account actions plus the unauthenticated Sign in action
- [x] 5.5 Remove fuel price and fuel-price movement from the implemented header contract
- [x] 5.6 Add documented `.pv-*` hooks and use runtime `--pv-*` values for all themeable header styling

## 6. Responsive and accessibility behavior

- [x] 6.1 Implement the one-row desktop header at 1024px and wider
- [x] 6.2 Implement the two-row tablet header from 640px through 1023px
- [x] 6.3 Implement the bounded mobile row and header-owned status drawer below 640px
- [x] 6.4 Add keyboard-operable navigation, theme, account, retry, sector, and status-drawer controls with visible focus
- [x] 6.5 Use readable duty text, semantic time markup, and non-chatty clock accessibility behavior

## 7. Automated verification

- [x] 7.1 Add Vue tests for every METAR state, missing station and timezone, active-sector states, duty states, clocks, theme modes, and account states
- [x] 7.2 Add Vue tests for desktop, tablet, and mobile information presence and status-drawer disclosure
- [x] 7.3 Run frontend typecheck, unit tests, and the production frontend build
- [x] 7.4 Run relevant PHP unit and feature tests
- [x] 7.5 Run the existing shared-Vue artifact test and addon extensibility browser test

## 8. Browser verification

- [x] 8.1 Verify the header on authenticated and public Inertia pages at desktop, tablet, and 390px
- [x] 8.2 Verify current, loading, missing, stale, error, retry, and station-change METAR behavior in the browser
- [x] 8.3 Verify light, dark, and auto mode, system preference changes, and no default-theme flash on hard reload
- [x] 8.4 Navigate across Dashboard, Flights, Bids, Logbook, PIREP detail, and Profile and confirm the persistent shell does not remount
- [x] 8.5 Confirm host, Nuxt UI providers, and addons use one Vue runtime
- [x] 8.6 Confirm the document has no horizontal overflow at 390px with long airline, pilot, route, and METAR values

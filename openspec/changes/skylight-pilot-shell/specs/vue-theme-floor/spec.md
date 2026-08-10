## ADDED Requirements

### Requirement: Persistent pilot dispatch header

The persistent `PvApp` shell SHALL render a pilot dispatch header based on `/html/body/header` of `mockups/e-dispatch.html`. It SHALL contain airline identity, active sector, duty status, METAR, UTC and local time, a light/dark/auto control, and pilot account controls. It SHALL NOT show fuel price or fuel-price movement.

#### Scenario: Authenticated desktop header

- **WHEN** an authenticated pilot opens a skylight Inertia page at desktop width
- **THEN** the persistent header shows airline identity, sector, duty, METAR, both clocks, theme control, and pilot identity in one row without fuel data

#### Scenario: Public Inertia header

- **WHEN** an unauthenticated visitor opens a public skylight Inertia page
- **THEN** the header uses application identity, omits private pilot operations, and shows a sign-in action

### Requirement: Pilot chrome uses typed shared Inertia props

The system SHALL share authenticated pilot identity and operational chrome through typed Inertia props. Persistent shell state SHALL NOT depend on dashboard, profile, PIREP, or other page-specific DTOs.

#### Scenario: Shared pilot identity

- **WHEN** an authenticated frontend Inertia response is built
- **THEN** `auth.user` contains id, name, avatar, ident, callsign, and airline identity

#### Scenario: Shared operational chrome

- **WHEN** an authenticated frontend Inertia response is built
- **THEN** `pilotChrome` contains nullable active sector, duty state, and weather station values

#### Scenario: Inertia page navigation

- **WHEN** the pilot navigates from Dashboard to Profile through Inertia
- **THEN** the shell reads the new shared props and never reads either page's local DTO for persistent chrome

### Requirement: Active sector and duty use the active PIREP

The active sector SHALL be the authenticated pilot's most recently updated PIREP in `IN_PROGRESS` or `PAUSED` state. Duty SHALL be on duty for `IN_PROGRESS`, paused for `PAUSED`, and off duty when no active PIREP exists. Saved bids SHALL NOT be treated as active sectors.

#### Scenario: Pilot is flying

- **WHEN** the pilot has one in-progress PIREP
- **THEN** the header shows its ident, departure, arrival, and on-duty state

#### Scenario: Pilot is paused

- **WHEN** the pilot's active PIREP is paused
- **THEN** the header shows the same sector with a paused duty state

#### Scenario: Pilot has no active PIREP

- **WHEN** the pilot has saved bids but no in-progress or paused PIREP
- **THEN** the header shows no active sector and off-duty state

#### Scenario: Pilot has multiple active PIREPs

- **WHEN** more than one in-progress or paused PIREP exists for the pilot
- **THEN** the most recently updated PIREP supplies the header sector deterministically

### Requirement: Weather station uses pilot location

The weather station SHALL use the pilot's current airport, then home airport. It SHALL be independent of the active dashboard page and active-sector selection.

#### Scenario: Current airport exists

- **WHEN** the pilot has a current airport
- **THEN** its ICAO and timezone supply the header weather station and local clock

#### Scenario: Current airport is absent

- **WHEN** the pilot has no current airport but has a home airport
- **THEN** the home airport supplies the header weather station and local clock

#### Scenario: No station exists

- **WHEN** neither current nor home airport is available
- **THEN** the header shows no station and makes no weather request

### Requirement: Header METAR uses the existing weather contract

The header SHALL fetch `GET /api/weather/{icao}` after the shell paints. The endpoint SHALL continue to use `AirportService` and the configured METAR provider. A successful response SHALL include the existing weather fields plus observation time and stale state.

#### Scenario: METAR loads

- **WHEN** the existing weather endpoint returns a current raw METAR
- **THEN** the header shows the station and raw METAR without blocking the page's first paint

#### Scenario: METAR is loading

- **WHEN** a station weather request is pending
- **THEN** the header reserves the weather region and shows a loading state

#### Scenario: METAR is missing

- **WHEN** a successful weather response contains a null METAR
- **THEN** the header shows `No METAR` and remains usable

#### Scenario: METAR is stale

- **WHEN** the observation is more than 90 minutes old
- **THEN** the header keeps the raw METAR visible and marks it stale with its observation time

#### Scenario: Weather request fails

- **WHEN** the weather endpoint returns 400 or 503
- **THEN** the header shows `METAR unavailable`, offers retry, hides provider exception text, and preserves navigation

#### Scenario: Station changes during a request

- **WHEN** shared props change the station before an earlier request completes
- **THEN** the earlier result does not replace weather for the new station

### Requirement: UTC and station-local clocks are client driven

The header SHALL show UTC and station-local time without a server request on each update. Local time SHALL use the shared airport timezone. Missing timezone SHALL render unavailable rather than a guessed offset.

#### Scenario: Both clocks are available

- **WHEN** the station includes a valid timezone
- **THEN** UTC and station-local time update at minute boundaries and use semantic time markup

#### Scenario: Station timezone is absent

- **WHEN** the station has no timezone
- **THEN** UTC remains available and local time is marked unavailable

#### Scenario: Assistive technology reads the clocks

- **WHEN** clock values update
- **THEN** the page does not announce every tick through an assertive or continuous live region

### Requirement: Header theme control supports light dark and auto

The header SHALL provide light, dark, and auto modes using the existing `skylight.theme` storage key. Auto SHALL follow system preference changes. The pre-paint resolver and Vue state SHALL agree before the first rendered frame.

#### Scenario: Explicit light or dark mode

- **WHEN** the pilot selects light or dark
- **THEN** the document mode changes immediately, persists, and remains through Inertia navigation and reload

#### Scenario: Auto mode

- **WHEN** auto is selected and the system color preference changes
- **THEN** the document follows the system preference without changing the saved mode from auto

#### Scenario: Hard reload

- **WHEN** a saved or auto mode page reloads
- **THEN** the first painted frame uses the resolved mode with no default-theme flash

### Requirement: Header account controls use persistent identity

Authenticated account controls SHALL use shared pilot identity and provide Profile and Sign out actions. Internal destinations SHALL use Inertia navigation where supported. Unauthenticated pages SHALL show Sign in.

#### Scenario: Authenticated account menu

- **WHEN** the pilot opens the account control
- **THEN** the menu shows avatar fallback, name, ident, callsign, Profile, and Sign out

#### Scenario: Profile navigation

- **WHEN** the pilot selects Profile
- **THEN** the Profile page opens through Inertia and the persistent shell does not remount

### Requirement: Header has intentional responsive hierarchy

The header SHALL use a one-row desktop layout, a two-row tablet layout, and a bounded mobile row with a header-owned status drawer. It SHALL provide a keyboard-operable navigation trigger and SHALL NOT cause horizontal page overflow at 390px.

#### Scenario: Desktop hierarchy

- **WHEN** the viewport is at least 1024px wide
- **THEN** all required dispatch fields are visible in one row

#### Scenario: Tablet hierarchy

- **WHEN** the viewport is from 640px through 1023px wide
- **THEN** brand, navigation, theme, and account occupy the first row while sector, duty, METAR, UTC, and local time occupy the second row

#### Scenario: Mobile hierarchy

- **WHEN** the viewport is narrower than 640px
- **THEN** the row shows navigation, airline mark, compact sector, UTC, theme, and account triggers and the status drawer exposes full duty, METAR, local time, and pilot identity

#### Scenario: 390px overflow check

- **WHEN** the header is rendered at a 390px viewport with long airline, pilot, route, and METAR values
- **THEN** the document has no horizontal overflow and every full value remains available in the status drawer

### Requirement: Persistent shell and shared Vue runtime remain intact

The dispatch header SHALL remain inside the existing `PvApp`, `UApp`, `UTheme`, and `AppShell` provider tree. Inertia page navigation SHALL preserve the shell, and host, Nuxt UI, and addon components SHALL use one Vue runtime.

#### Scenario: Navigation preserves shell state

- **WHEN** the pilot navigates across two skylight Inertia pages
- **THEN** the header, theme mode, open provider context, and extension context are not recreated as page-local state

#### Scenario: Addon renders beside header

- **WHEN** an addon Vue component renders through the existing extension system
- **THEN** it uses the same Vue runtime and Nuxt UI provider tree as the host shell

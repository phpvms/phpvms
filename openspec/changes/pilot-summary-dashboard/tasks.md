## 1. Dashboard data contract

- [x] 1.1 Expand `RankProgressData` with current hours, target hours, and hours remaining, using the transfer-hours setting in the rank calculation
- [x] 1.2 Add an immutable nullable `scheduled_arrival_at` UTC timestamp for PIREPs created from scheduled flights and preserve it in the PIREP archive
- [x] 1.3 Derive scheduled arrival from the service date, structured flight times, and airport timezones, including overnight and cross-timezone routes
- [x] 1.4 Add `StateBadgeData`, formatted transfer time, pilot score, nullable on-time percentage, and average landing rate to `DashboardData`
- [x] 1.5 Read pilot score, on-time percentage, and average landing rate from accepted PIREPs and return null when no measurable values exist
- [x] 1.6 Calculate on-time percentage as accepted scheduled arrivals before the 15-minute threshold divided by eligible accepted scheduled PIREPs; count accepted diversions late and exclude rows without a schedule snapshot
- [x] 1.7 Keep existing dashboard identity and widget props compatible while using the shell's shared `auth.user` fields in the fixed summary
- [x] 1.8 Regenerate TypeScript declarations and run the existing type-drift test

## 2. Fixed pilot summary

- [x] 2.1 Expand `DashboardPilotHeader.vue` to match only `section#profile` of `mockups/b-workspace.html`
- [x] 2.2 Keep the summary before `DashboardToolbar.vue` and `DashboardBoard.vue` in `DashboardWorkspace.vue`
- [x] 2.3 Add accessible pilot-status text and rank-progress semantics
- [x] 2.4 Add wide seven-column, tablet four-column, and mobile two-column metric layouts with no truncation or horizontal scrolling
- [x] 2.5 Render `—` for unavailable score, on-time, landing-rate, balance, location, and rank-target values

## 3. Shared awards presentation

- [x] 3.1 Replace the profile-only award card markup with a focused shared component that consumes `AwardData[]`
- [x] 3.2 Match `section#awards` of `mockups/a-operations-console.html`: compact cards, icon tile, title, qualifier, earned state, and count
- [x] 3.3 Add two/three/six-column responsive behavior, wrapped long text, a missing-image fallback, and a zero-awards empty state
- [x] 3.4 Use the shared component from `ProfileAwards.vue` and any existing pilot surface that displays awards

## 4. Backend verification

- [x] 4.1 Add DTO tests for minimum, normal, and maximum stored values
- [x] 4.2 Add aggregate tests for accepted-only scores, null scores, zero landing rates, no PIREPs, and rounded results
- [x] 4.3 Add rank tests for transfer hours enabled and disabled, missing rank, highest rank, and values beyond the target
- [x] 4.4 Cover on-time, exactly-15-minutes-late, early, late, diverted, overnight, cross-timezone, schedule-edited, and no-eligible-PIREP cases
- [x] 4.5 Run the relevant PHP unit and feature tests

## 5. Frontend verification

- [x] 5.1 Add Vue tests for normal, minimum, maximum, null, and highest-rank summary fixtures
- [x] 5.2 Add Vue tests for awards with images, missing and failed images, long text, and no awards
- [x] 5.3 Run frontend typecheck, unit tests, and the production frontend build
- [x] 5.4 Run the existing shared-Vue artifact test and addon extensibility browser test

## 6. Browser verification

- [x] 6.1 Verify summary and awards at desktop, tablet, and 390px with no horizontal overflow
- [x] 6.2 Verify light, dark, and auto mode and confirm no default-theme flash on hard reload
- [x] 6.3 Verify persistent Inertia navigation does not remount the shell or move the fixed summary into the widget board
- [x] 6.4 Verify the host, Nuxt UI providers, and addons use one Vue runtime

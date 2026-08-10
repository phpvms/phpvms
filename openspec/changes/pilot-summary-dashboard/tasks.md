## 1. Dashboard data contract

- [ ] 1.1 Expand `RankProgressData` with current hours, target hours, and hours remaining, using the transfer-hours setting in the rank calculation
- [ ] 1.2 Add `StateBadgeData`, formatted transfer time, pilot score, nullable on-time percentage, and average landing rate to `DashboardData`
- [ ] 1.3 Read pilot score and average landing rate with one accepted-PIREP aggregate query and return null when no measurable values exist
- [ ] 1.4 Keep existing dashboard identity and widget props compatible while using the shell's shared `auth.user` fields in the fixed summary
- [ ] 1.5 Regenerate TypeScript declarations and run the existing type-drift test

## 2. Fixed pilot summary

- [ ] 2.1 Expand `DashboardPilotHeader.vue` to match only `section#profile` of `mockups/b-workspace.html`
- [ ] 2.2 Keep the summary before `DashboardToolbar.vue` and `DashboardBoard.vue` in `DashboardWorkspace.vue`
- [ ] 2.3 Add accessible pilot-status text and rank-progress semantics
- [ ] 2.4 Add wide seven-column, tablet four-column, and mobile two-column metric layouts with no truncation or horizontal scrolling
- [ ] 2.5 Render `—` for unavailable score, on-time, landing-rate, balance, location, and rank-target values

## 3. Shared awards presentation

- [ ] 3.1 Replace the profile-only award card markup with a focused shared component that consumes `AwardData[]`
- [ ] 3.2 Match `section#awards` of `mockups/a-operations-console.html`: compact cards, icon tile, title, qualifier, earned state, and count
- [ ] 3.3 Add two/three/six-column responsive behavior, wrapped long text, a missing-image fallback, and a zero-awards empty state
- [ ] 3.4 Use the shared component from `ProfileAwards.vue` and any existing pilot surface that displays awards

## 4. Backend verification

- [ ] 4.1 Add DTO tests for minimum, normal, and maximum stored values
- [ ] 4.2 Add aggregate tests for accepted-only scores, null scores, zero landing rates, no PIREPs, and rounded results
- [ ] 4.3 Add rank tests for transfer hours enabled and disabled, missing rank, highest rank, and values beyond the target
- [ ] 4.4 Run the relevant PHP unit and feature tests

## 5. Frontend verification

- [ ] 5.1 Add Vue tests for normal, minimum, maximum, null, and highest-rank summary fixtures
- [ ] 5.2 Add Vue tests for awards with images, missing and failed images, long text, and no awards
- [ ] 5.3 Run frontend typecheck, unit tests, and the production frontend build
- [ ] 5.4 Run the existing shared-Vue artifact test and addon extensibility browser test

## 6. Browser verification

- [ ] 6.1 Verify summary and awards at desktop, tablet, and 390px with no horizontal overflow
- [ ] 6.2 Verify light, dark, and auto mode and confirm no default-theme flash on hard reload
- [ ] 6.3 Verify persistent Inertia navigation does not remount the shell or move the fixed summary into the widget board
- [ ] 6.4 Verify the host, Nuxt UI providers, and addons use one Vue runtime

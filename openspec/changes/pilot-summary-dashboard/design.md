## Context

`DashboardWorkspace.vue` already renders `DashboardPilotHeader.vue` before `DashboardToolbar.vue` and `DashboardBoard.vue`. The current header only shows name, current rank, leave state, and station. `DashboardData` already exposes flights, formatted flight time, leave state, balance, current airport, last PIREP, rank progress, and route data.

The earlier plan used `mockups/c-analytics.html` and depended on `user_stats` tables. Neither table nor model exists in this worktree. The locked authority is now only `section#profile` of `mockups/b-workspace.html`.

`ProfileAwards.vue` already consumes `ProfileData.awards`, which is a list of `AwardData` values with name, description, and image. Its current presentation does not match the locked compact awards grid.

## Goals / Non-Goals

**Goals:**

1. Render the exact pilot-summary information named by the locked `section#profile` contract.
2. Keep the summary fixed above the customizable widget board.
3. Give every DTO field one verified source.
4. Reuse shared pilot identity from the persistent shell.
5. Provide one compact awards presentation for all pilot award surfaces.
6. Preserve light, dark, auto, mobile, addon, and runtime-theme support.

**Non-Goals:**

1. Do not copy the rest of `b-workspace.html` into the dashboard.
2. Do not add monthly charts, sparklines, trend deltas, or promotion estimates.
3. Do not create the Branding admin page or the themes.phpvms.net builder.
4. Do not make dashboard data responsible for persistent header chrome.
5. Do not calculate on-time performance from the current mutable `Flight` row.

## Decisions

### D1. Expand the existing fixed dashboard component

Keep `DashboardPilotHeader.vue` in its current position inside `DashboardWorkspace.vue`. Expand that component instead of adding a second summary above it. The toolbar and `DashboardBoard.vue` remain below the fixed summary and keep their current customization behavior.

### D2. Keep one source for each displayed value

| Display value                                | DTO field                          | Source                                                                                              |
| -------------------------------------------- | ---------------------------------- | --------------------------------------------------------------------------------------------------- |
| Pilot name, avatar, ident, callsign, airline | shared `auth.user`                 | authenticated `User` and `User.airline` from `HandleInertiaRequests`                                |
| Pilot status                                 | `state`                            | `User.state`, projected as `StateBadgeData`                                                         |
| Current location                             | existing `currentAirport`          | `User.curr_airport_id`, then `User.home_airport_id`                                                 |
| Flights                                      | existing `flights`                 | `User.flights`                                                                                      |
| Flight hours                                 | existing `flightTimeMinutes`       | `User.flight_time`                                                                                  |
| Transfer hours                               | `transferTimeMinutes`              | `User.transfer_time`                                                                                |
| Balance                                      | existing `balance`                 | current `journal.balance`                                                                           |
| Current rank and next rank                   | expanded `rank.from` and `rank.to` | `User.rank` and the next `Rank` ordered by hours                                                    |
| Current rank hours                           | `rank.currentHours`                | `User.flight_time`, plus `User.transfer_time` only when `pilots.count_transfer_hours` is enabled    |
| Target rank hours                            | `rank.targetHours`                 | next `Rank.hours`                                                                                   |
| Hours remaining                              | `rank.hoursRemaining`              | non-negative target minus current rank hours                                                        |
| Rank percentage                              | `rank.pct`                         | current rank hours divided by target rank hours, clamped to 0–100                                   |
| Pilot score                                  | `pilotScore`                       | rounded average of non-null `score` from the pilot's accepted PIREPs                                |
| On-time percentage                           | `onTimePercentage`                 | accepted scheduled PIREPs with actual block-on before snapshotted scheduled arrival plus 15 minutes |
| Average landing rate                         | `averageLandingRate`               | rounded average of non-null, non-zero `landing_rate` from the pilot's accepted PIREPs               |

Existing `DashboardData.id` and `DashboardData.name` stay for compatibility with current page-prop and addon contracts. The new summary does not add more identity fields to `DashboardData`.

### D3. Use one accepted-PIREP aggregate query

Read pilot score and average landing rate in one aggregate query filtered by `user_id` and `PirepState::ACCEPTED`. A missing aggregate returns null. The UI renders `—`, not zero.

This replaces the stale dependency on `user_stats` and `user_stats_monthly`. A later materialized-statistics implementation may replace the query behind the DTO without changing the frontend contract.

### D4. Use the standard arrival on-time rule

Use arrival performance because it measures whether the pilot completed the scheduled operation on time. The US Bureau of Transportation Statistics defines an on-time arrival as a gate arrival less than 15 minutes after scheduled arrival. EUROCONTROL also reports arrival punctuality against a 15-minute scheduled-arrival threshold.

For each scheduled PIREP, persist an immutable `scheduled_arrival_at` UTC timestamp when the PIREP is created from a flight. Derive it from the flight's structured schedule, the departure service date, and the departure and arrival airport timezones. Preserve it when the source flight is later edited or deleted.

For accepted scheduled PIREPs with both `scheduled_arrival_at` and `block_on_time`:

1. An arrival is on time when `block_on_time < scheduled_arrival_at + 15 minutes`.
2. An arrival at exactly 15 minutes late is late.
3. Early arrivals are on time.
4. Accepted diverted operations are late, matching the standard arrival-performance treatment.
5. Manual or legacy PIREPs without a schedule snapshot are excluded from the denominator.
6. `onTimePercentage` is the on-time count divided by the eligible count, multiplied by 100 and rounded to one decimal place.
7. The value is null when there are no eligible scheduled PIREPs.

References:

1. US Bureau of Transportation Statistics, Airline On-Time Performance and Causes of Flight Delays: https://www.bts.gov/explore-topics-and-geography/topics/airline-time-performance-and-causes-flight-delays
2. EUROCONTROL Data Snapshot 44: https://www.eurocontrol.int/publication/eurocontrol-data-snapshot-44-causes-flight-delays

### D5. Keep the summary presentation focused

The summary keeps the reference order: identity and status, rank progress, then seven career metrics. Identity and rank progress stack on narrow screens. Metrics use two columns on mobile, four on tablet, and seven on wide desktop. Values and labels wrap without truncation or horizontal scrolling.

The rank bar uses an accessible progress element or equivalent `progressbar` semantics. Pilot status has visible text and does not rely on a colored dot.

### D6. Use one shared awards component

Extract a focused awards component that accepts `AwardData[]`. Profile uses it instead of owning a separate card implementation. Any later pilot-awards surface uses the same component.

The component keeps the locked compact card structure: icon tile, title, qualifier, earned state, and a two/three/six-column grid. Text wraps. A missing image uses a neutral award icon. Zero awards shows a small empty state. The count comes from the array length.

### D7. Preserve the theme and extension contracts

Use Nuxt UI directly for generic controls. Use `--pv-*` variables and stable `.pv-*` hooks for phpVMS-owned summary and award components. Keep structure and responsive rules in the application bundle. Do not put layout classes in runtime theme JSON.

The summary remains part of the host dashboard, outside the customizable widget board. The widget board and addon slots keep receiving the current page props.

### D8. Keep persistent chrome separate

`skylight-pilot-shell` owns airline identity, active sector, duty state, weather station, clocks, theme controls, and account controls. The dashboard owns career metrics and rank progress. The dashboard must not become the source for shell state during Inertia navigation.

## Risks / Trade-offs

1. Direct PIREP aggregates can grow with history. Mitigation: use one query filtered by indexed pilot and state fields, verify the query shape, and keep the DTO contract stable for a later materialized source.
2. Legacy and manual PIREPs do not have an immutable scheduled-arrival timestamp. Mitigation: exclude them from the denominator and show `—` when no eligible scheduled PIREPs remain.
3. Existing addons may read current flat dashboard props. Mitigation: keep existing fields and add only the new page fields.
4. Seven metrics can crowd narrow screens. Mitigation: use the verified two-column mobile layout and test long localized labels at 390px.

## Migration Plan

1. Implement `skylight-pilot-shell` first so shared pilot identity is available.
2. Extend the dashboard DTO and generated TypeScript types.
3. Expand the existing fixed dashboard header.
4. Replace the profile-only award cards with the shared awards component.
5. Verify PHP, frontend, theme, addon, and browser behavior before release.

Rollback removes the new DTO fields and restores the current dashboard header and profile awards component. The customizable widget board remains unchanged.

## Open Questions

1. None.

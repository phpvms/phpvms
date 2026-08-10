## Why

The dashboard has a fixed pilot header, but it does not expose the full pilot summary shown in `section#profile` of `mockups/b-workspace.html`. The summary needs one typed contract, a clear source for every value, and a fixed place above the customizable widget board.

## What Changes

1. Expand the existing dashboard pilot header into the fixed pilot summary. Keep it above the dashboard toolbar and widget board.
2. Use only `section#profile` of `mockups/b-workspace.html` as the dashboard presentation authority.
3. Expose pilot status, transfer time, absolute rank hours, pilot score, on-time percentage, and average landing rate through `DashboardData` and `RankProgressData`.
4. Keep identity in the shared authenticated-user props defined by `skylight-pilot-shell`. Do not add a second dashboard-only identity projection.
5. Source stored values from `User` and `journal`. Source pilot score and average landing rate from accepted PIREPs. Calculate on-time percentage from scheduled operations using the standard arrival rule: actual block-on earlier than scheduled arrival plus 15 minutes.
6. Add one shared awards presentation based on `section#awards` of `mockups/a-operations-console.html`. Use it on Profile and any other pilot surface that displays awards. Do not place awards inside the fixed dashboard summary.
7. Add responsive, theme, accessibility, empty-state, and realistic-data coverage for the summary and awards.

## Capabilities

### New Capabilities

1. `pilot-summary-dashboard`: The fixed dashboard pilot summary, its typed data contract, and the shared pilot-awards presentation.

### Modified Capabilities

1. None.

## Impact

1. Backend DTOs: `app/Http/Data/DashboardData.php`, `app/Http/Data/RankProgressData.php`, and the generated TypeScript declarations.
2. Dashboard UI: `resources/js/apps/fe-vue/src/widgets/dashboard/DashboardWorkspace.vue` and `DashboardPilotHeader.vue`.
3. Awards UI: `resources/js/apps/fe-vue/src/widgets/profile/ProfileAwards.vue` or a focused shared replacement that consumes `AwardData`.
4. Data sources: `User`, `Rank`, `journal`, accepted `Pirep` rows, and the shared authenticated-user props from `HandleInertiaRequests`.
5. No new frontend package or charting dependency.

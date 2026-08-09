# Upgrading phpvms

## Unreleased — live map positions

The live map now reads from a new `pirep_positions` table — one row per flight —
instead of resolving the newest `acars` breadcrumb for every flight on every
poll. A row in that table _is_ what puts a flight on the map.

### ⚠ Orphaned ACARS rows are permanently deleted

`PirepService::delete()` has always listed `acars` as a child table it removes,
and never removed it. There was no foreign key either, so **every install that
has ever hard-deleted a PIREP is carrying `acars` rows whose PIREP no longer
exists.** `add_acars_pirep_foreign_key` counts those rows, logs the number, and
deletes them in batches of 10,000 before adding the constraint — the database
rejects the constraint while they exist.

**This cannot be undone.** Rolling the migration back drops the foreign key; it
does not bring the rows back. Take a backup first if you want them.

The rows were already unreachable: nothing joins `acars` to a PIREP that is not
there. Soft-deleted PIREPs are _not_ affected — the PIREP row still exists, so
its telemetry is not orphaned.

On a synthetic 2,000,000-row `acars` table with 1,000,000 orphans the whole
migration took about 45 seconds on MySQL 8. Time scales with your orphan count,
which nothing can predict in advance.

SQLite cannot add a foreign key to an existing table, so the constraint is
skipped there and telemetry cleanup relies on the service layer alone. The
orphan purge and the column widening still apply.

### Behaviour changes you will notice

- **Prefiled flights now appear on the map before they move**, stationary at
  their departure airport. Previously a flight was invisible until its first
  position report.
- **Completed and paused flights stay on the map for a configured period.**
  Previously a completed flight vanished the instant its PIREP left
  `IN_PROGRESS`.
- **An administrator editing a PIREP no longer keeps a dead flight on the map.**
  Liveness is now measured on the position row, which only position reports
  touch.

### Settings

`acars.live_time` did two unrelated jobs. It has been split, and the live map's
display settings have moved out of the ACARS group into a new **Live map** group.
Your configured values are carried across automatically.

| Old key                 | New key                   | Unit    | Default |
| ----------------------- | ------------------------- | ------- | ------- |
| `acars.live_time`       | `pireps.tombstone_time`   | hours   | 12      |
| —                       | `livemap.live_time`       | minutes | 30      |
| —                       | `livemap.idle_time`       | minutes | 60      |
| `acars.center_coords`   | `livemap.center_coords`   |         |         |
| `acars.default_zoom`    | `livemap.default_zoom`    |         |         |
| `acars.update_interval` | `livemap.update_interval` |         |         |

`pireps.tombstone_time` keeps hours and keeps your number — it governs only when
a silent in-progress PIREP is cancelled. The two new settings are in minutes and
govern only the map: how long a finished flight stays drawn, and how long a
flight that is not moving stays drawn (a paused one, or one prefiled and not yet
departed).

### For addon authors

`App\Enums\PirepStatus` is deprecated in favour of `App\Enums\PirepPhase`. It is
a `class_alias`, not a second enum, so `PirepStatus::TAXI` and
`PirepPhase::TAXI` are the same case — identity comparison, `instanceof` and
existing model casts all keep working, and no stored value changes. No database
column was renamed; `pireps`.`status` and `acars`.`status` are untouched.

`Pirep::position()` now returns a `PirepPosition`, not an `Acars`.
`Pirep::scopeActiveFlights()` is gone: use `Pirep::onLiveMap()` for the map, or
`Pirep::silentInProgress($hours)` for the reaper's meaning.

## Unreleased — Laravel Passport (OAuth2) API authentication

The API can now be authenticated with OAuth2 (Laravel Passport) in addition to
the legacy per-user API key. **Existing API keys keep working unchanged** — the
`api.auth` middleware tries a Passport bearer token first and falls back to the
legacy `api_key` lookup, and legacy keys retain full access.

### What happens automatically

Running the web updater (or installer) applies the pending migrations — which
add the Passport `oauth_*` tables — seeds a personal-access client, and
**generates the Passport signing keys if they aren't already present**. A
standard upgrade therefore needs no manual OAuth steps.

### Multi-node / Octane deployments

Provide the keys via env so every node shares the same pair — set
`PASSPORT_PRIVATE_KEY` / `PASSPORT_PUBLIC_KEY` (see `.env.example`). When these
are set, the installer/updater leaves them alone and generates nothing.

### Manual / CLI

If you provision from the CLI instead of the web updater:

```bash
php artisan migrate
php artisan passport:keys                    # or set the env keys above
php artisan passport:client --personal       # if no personal-access client exists
```

`composer run setup` runs these for fresh installs.

See [API Authentication](api-authentication.md) for scopes, personal access
tokens, PKCE and the migration path off legacy keys.

## Unreleased — schema modernization for RouteForge prerequisites

This release introduces four schema/behavior changes. Two are **required actions**, two are **optional one-shot commands**.

### Required actions

#### 1. Run pending migrations

```bash
php artisan migrate
```

New migrations:

- `add_time_columns_to_flights_table` — adds `departure_time TIME`, `arrival_time TIME` to `flights`
- `add_capability_columns_to_subfleets_table` — adds `cruise_speed`, `max_range_nm`, `route_types`
- `create_flight_bundles_table` — new `flight_bundles` table
- `add_bundle_columns_to_flights_table` — adds `bundle_id`, `bundle_cascade_deleted_at` to `flights`
- `seed_default_flight_bundle` — seeds the default bundle and backfills every existing flight to it
- `rename_active_to_enabled_on_flights_table` — renames `flights.active` → `flights.enabled`
- `add_visibility_indexes_to_flights_table` — adds three composite indexes for visibility read paths

#### 2. Backfill flight time columns

```bash
php artisan flights:migrate-time-columns
```

Parses legacy `dpt_time` / `arr_time` string columns into the new `departure_time` / `arrival_time` `TIME` columns. Idempotent; safe to re-run. Failures are logged to `storage/logs/flight-time-migration.log`.

### Optional actions

#### 3. Preserve hidden-visibility intent (opt-in)

```bash
php artisan phpvms:preserve-hidden-visibility
```

Disables (`enabled = false`) any flight whose pre-migration `visible` value was `false`. Use this only if your prior workflow used the now-removed Filament "Visible" toggle to hide flights. Idempotent.

After this command runs, `flights.visible` becomes fully cron-managed (computed by `SetVisibleFlights`) and should never be written by admin code.

### Behavior changes for module developers

#### `Flight::active()` scope deprecated

The `Flight::active()` query scope is now a deprecated delegate to `Flight::visible()`. Both produce identical SQL. The deprecated scope emits a `trigger_deprecation` notice on invocation.

```php
// before
Flight::active()->get();

// after
Flight::visible()->get();
```

`Flight::active()` will be removed in the next major version.

#### `flights.active` renamed to `flights.enabled`

The boolean column previously stored as `active` is now `enabled`. Mass-assignment, casts, and form fields use the new name. Raw queries against `flights.active` will fail at SQL execution time.

```php
// before
Flight::where('active', true)->...

// after
Flight::where('enabled', true)->...
// or, for pilot-facing reads
Flight::visible()->...
```

#### `flights.visible` is cron-managed

`flights.visible` is now the cron-computed combined state of `flight.enabled AND bundle.enabled AND in_effective_window`. Admin code SHALL NOT write to it directly. The Filament `FlightForm` no longer exposes a Visible toggle.

The combined state is recomputed:

- nightly by `App\Cron\Nightly\SetVisibleFlights`
- synchronously by `BundleObserver::saved` / `BundleObserver::restored` whenever a bundle is created, has its `enabled` / `start_date` / `end_date` changed, or is restored

#### `App\Cron\Nightly\SetActiveFlights` removed

Replaced by `App\Cron\Nightly\SetVisibleFlights`. The new cron has two passes: bundles first (computes `flight_bundles.visible`), then flights (computes `flights.visible` using effective window — bundle window takes precedence when set, else flight window, else always-visible).

**Note**: The legacy `SetActiveFlights` also filtered by `flights.days` (day-of-week bitmask). `SetVisibleFlights` does NOT honor `days`. If your virtual airline relied on day-of-week scheduling to hide flights, those flights will now be visible on every day of the week. Audit `flights.days` usage post-upgrade.

#### `FlightResource` API: `active` retained as deprecated alias

`App\Http\Resources\FlightResource` JSON output continues to include both `enabled` (new source of truth) and `active` (alias of `enabled`). The `active` alias is marked `@deprecated` and will be removed in the next major version. Update API consumers accordingly.

### New: Route bundles

Flights now belong to a `FlightBundle`. Every existing flight is backfilled to a seeded bundle named `"Default"`. The seeded bundle has no special protection — admins may rename, disable, or delete it like any other bundle (deleting requires reassigning child flights first since `bundle_id` has a `restrictOnDelete` foreign key).

Bundles can carry their own `start_date` / `end_date`. When a bundle has any date set, the bundle's window **overrides** the flight's own dates for visibility computation. The Filament `FlightForm` hides the per-flight date pickers when the chosen bundle owns dates.

See `app/Filament/Resources/FlightBundles/` for admin UI and `openspec/changes/schema-modernization-for-routeforge/specs/route-bundles/spec.md` for the full requirements.

### New: Subfleet operational capability

The `subfleets` table gained three optional columns: `cruise_speed` (knots), `max_range_nm` (nautical miles), `route_types` (`Collection<FlightType>` via cast). NULL means "unrestricted." Defaults for unset values come from `config('phpvms.routeforge.*')`.

These columns are admin-only metadata today; the upcoming RouteForge change will consume them for mesh generation.

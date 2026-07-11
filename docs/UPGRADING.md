# Upgrading phpvms

## Unreleased — Laravel Passport (OAuth2) API authentication

The API can now be authenticated with OAuth2 (Laravel Passport) in addition to
the legacy per-user API key. **Existing API keys keep working unchanged** — the
`api.auth` middleware tries a Passport bearer token first and falls back to the
legacy `api_key` lookup, and legacy keys retain full access.

### Required actions

#### 1. Run pending migrations

```bash
php artisan migrate
```

Adds the Passport `oauth_*` tables (clients, access/refresh/auth codes, device codes).

#### 2. Provision Passport encryption keys

For a single-server install, generate the key files:

```bash
php artisan passport:keys
```

For multi-node / ephemeral-filesystem / Octane deployments, set the keys via env
instead (so every node shares them) — see `PASSPORT_PRIVATE_KEY` /
`PASSPORT_PUBLIC_KEY` in `.env.example`. `composer run setup` runs
`passport:keys` automatically for fresh installs.

The `DatabaseSeeder` seeds a personal-access client automatically; for an
existing install without one, create it with `php artisan passport:client --personal`.

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

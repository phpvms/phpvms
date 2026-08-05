# Upgrading to phpVMS 8.0 — categorized change inventory (DRAFT)

Working draft for the 7.x → 8.0 upgrade guide. Baseline: `7.0.10` → `main`
(as of the addon registry browser merge, #2294). Scope: core application
features and behavior. The addon/module developer migration gets its own guide
(see "Next" at the bottom) — this document only touches module changes where an
operator or API consumer would feel them.

Status: **categorization draft for review**. Each entry is a heading +
one-liner. Before this becomes the final guide, every entry needs a "What to
do" paragraph in the style of the better-auth upgrade guide
(https://better-auth.com/docs/guides/1-7-upgrade-guide).

---

## 1. Major new features and additions

### 1.1 The admin panel is now Filament 5

The custom-built admin (27 Blade controllers in 7.x) is gone. All admin UI —
airlines, airports, aircraft, subfleets, flights, PIREPs, users, roles,
settings, finances, importer, installer — was rebuilt as Filament 5 resources,
pages and widgets, served at `/admin`. New admin capabilities that came with
it: import/export actions, bulk-add modals, drag-and-drop uploads, a dashboard
with logo/version, the log viewer, and a backup UI (spatie/laravel-backup +
Filament plugin).

Operator impact: new look and interaction model; admin routes/URLs from 7.x
are gone. Addon impact: huge (deferred to the addon guide).

### 1.2 Addon Manager module + addon registry browser

New bundled module (`modules/phpvms-addon-manager`, PR #2294): browse the
phpVMS addon registry from the admin panel, install / update / validate addons
in-place, compatibility evaluation against the running version, registry cache,
`addons:relink` command, `Addon` / `AddonSetting` models. This replaces the old
"modules" admin page and the manual upload workflow.

### 1.3 OAuth2 API authentication (Laravel Passport)

The API can now be authenticated with OAuth2 / Laravel Passport:
personal-access clients, an `api_key` grant, permission-backed API scopes
(`scope:` middleware on every protected route), short-lived access tokens with
rotating refresh tokens. Admin can manage OAuth clients; pilots get an "API
connections" UI in their profile. Legacy per-user API keys keep working
unchanged (fallback in `api.auth`).

Verified against `routes/api.php`: the endpoint surface is unchanged from 7.x
except two details — `GET airports/{id}` became `GET airports/{airport}`
(route-model binding, response identical), and `POST users/simbrief_username`
is new (scope `settings:write`). Every authenticated route now carries exactly
one `scope:` middleware. The nine scopes: `airlines:read`, `airports:read`,
`bids:write`, `fleet:read`, `flights:read`, `pireps:read`, `pireps:write`,
`settings:write`, `user:read`.

See `docs/api-authentication.md`.

### 1.4 Roles & permissions rewritten (laratrust → spatie/laravel-permission)

Role/permission model replaced by `spatie/laravel-permission` + a core
`Ability` enum, seeded roles/permissions (RolesPermissionsSeeder + data
migration), a permission sync + policy generation command, a super-admin gate,
and a role permission-matrix UI in Filament. Permission strings change from the
old laratrust naming.

### 1.5 Live map rewritten on a positions table

The live map now reads `pirep_positions` (one row per flight) instead of
resolving the newest ACARS breadcrumb per poll. Prefiled flights appear before
they move; finished/paused flights stay on the map for a configurable period;
liveness is measured on the position row. ACARS orphans are purged (see
Breaking). Full detail already in `docs/UPGRADING.md`.

### 1.6 Route bundles + flight visibility overhaul

New `flight_bundles` table; every existing flight is backfilled to a seeded
"Default" bundle. `flights.active` → `flights.enabled`; `flights.visible` is
now cron-managed (`SetVisibleFlights`), computed from enabled × bundle enabled
× effective window. Bundle-level subfleets are inherited by member flights.
Full detail already in `docs/UPGRADING.md`.

### 1.7 RouteForge foundations

Schema groundwork for the RouteForge mesh generator: `departure_time` /
`arrival_time` TIME columns on flights (with a `flights:migrate-time-columns`
backfill command), subfleet operational capability columns (`cruise_speed`,
`max_range_nm`, `route_types`), admin batch flight composer, mesh config
defaults in `config/phpvms.php`. Full detail already in `docs/UPGRADING.md`.

### 1.8 PIREP telemetry & typed custom fields

PIREP custom field values can now carry a type: `pirep_field_values` gained
`type` (NUMBER / TEXT / TIMESTAMP / BOOLEAN, nullable — null means untyped
plain string) and `units` (AIXM unit code) columns, and the model exposes a
`typed_value` accessor that casts `value` per the declared type. New sim
metadata: PIREP `sim_type`, ACARS sim-state columns, engine N1/N2 average
percentages, flight phase as a raw string. `acars.log` widened to `text`;
`acars.distance` to `double`; orphan purge before a new FK (see Breaking).

### 1.9 Automatic distance-based fare pricing

Fares can price themselves from distance × category × airline: new auto-price
columns on fares/subfleets/airlines, admin UI, computed PIREP fare price.

### 1.10 FrankenPHP + Laravel Octane deployment stack

Production Docker stack: `compose.deploy.yml`, `Dockerfile.prod`, FrankenPHP
with an Octane runtime mode (serversideup base image), `laravel/octane` as a
production dependency. Local/sail/test compose files split out.

### 1.11 Foundation upgrades

Laravel 10 → 13, PHP `>=8.4.1` (was `>=8.1`), Filament 5, Vite replaces
webpack.mix.js, bun replaces npm/yarn (bun.lock), Pest 4 replaces PHPUnit,
Larastan/Rector/Pint static stack, `composer test` full check suite.

---

## 2. Smaller features and additions

- **Installer**: auto-advance past the migration step, auto-skip/auto-run,
  APP_KEY warning for the default key, Passport keys generated automatically
  when absent.
- **SimBrief**: searchable airframe dropdown, tolerates empty TLR.
- **Airports**: bulk-add modal + Enter-to-lookup on ICAO.
- **Airlines**: drag-and-drop logo upload.
- **Flights**: briefing fast-path (`with=bid`, per-request setting memo),
  inherited-subfleet cap and reporting.
- **Fares/finance**: `airline_id` searchable + ordered options; aircraft
  weights rounded in forms.
- **Import/export**: aircraft import/export re-added; subfleet CSV
  import/export includes type ratings.
- **ACARS**: log/event strings capped at 1000 chars; engine telemetry
  recording.
- **Notifications**: Discord announcements via `laravel-discord-notifier`.
- **Filament ergonomics**: form actions right-aligned, cancel-before-save;
  per-module Filament panels with a panel switcher.
- **API**: user resource now returns role names + effective permissions;
  token response includes roles/permissions; `flights/{id}` by-id search
  visibility fix.
- **Admin niceties**: relative URLs accepted; log-viewer assets published on
  composer update; maintenance commands run via `Process` instead of raw
  Artisan calls; `view:modules` as a core permission.
- **Dev tooling**: ide-helper regenerated on install, Pint/Rector/Larastan
  wired into CI, Docker major-minor version tags.
- **Settings**: live map display settings moved to a new "Live map" group
  (`acars.live_time` split — see Breaking); Discord webhook-url settings
  renamed to route settings (values carried over); languages/gravatar config
  consolidated into `config/phpvms.php`.

---

## 3. Breaking changes

### 3.1 Requirements

- **PHP >= 8.4.1** required (was >= 8.1). Extensions unchanged (json,
  mbstring, simplexml, bcmath, pdo, intl, zip).
- **Laravel 10 → 13** (v13.15.0 in the lockfile): any package or custom code
  pinned to Laravel 10 APIs must be re-verified (framework majors 11/12/13 in
  between). App bootstrap moved to `bootstrap/app.php` — there is no
  `app/Http/Kernel.php` or middleware-groups config anymore; the health route
  is `/up`.
- **Front-end toolchain**: `webpack.mix.js` removed → Vite + bun. Custom
  theme/front-end builds must be ported; `bun run build` is the new build
  command.
- **Env var renames** (old names silently ignored — 8.0's config files only
  read the new ones): `QUEUE_DRIVER` → `QUEUE_CONNECTION`, `CACHE_DRIVER` →
  `CACHE_STORE`, `MAIL_DRIVER` → `MAIL_MAILER` (new default `log`),
  `BROADCAST_DRIVER` → `BROADCAST_CONNECTION`.
- **Changed defaults when no env var is set**: cache `array` → `database`,
  queue `sync` → `database`, session `file` → `database`, DB connection
  `mysql` → `sqlite`, mailer `smtp` → `log`. Bring your old `.env` values
  across under the new names.
- **New env vars**: `APP_LOCALE`, `TRUSTED_PROXIES` (default `*`),
  `PASSPORT_PRIVATE_KEY` / `PASSPORT_PUBLIC_KEY` (required on multi-node /
  Octane), `OCTANE_HTTPS`, `VITE_APP_NAME`, `MAIL_SCHEME`,
  `RUN_QUEUED_JOBS_IN_CRON` (moved from `QUEUE_WORKER`).

### 3.2 Admin panel

- All 7.x custom admin controllers, routes, views and URLs are gone; the
  admin is now Filament at `/admin`. Anything (scripts, bookmarks, addon
  links) hitting the old admin URLs breaks.

**URL map (7.x → 8.0).** Filament resource slugs are plural model names unless
noted; resources are grouped by navigation group.

| 7.x URL (`/admin/…`)                             | 8.0 (`/admin/…`)                                                                                                                              |
| ------------------------------------------------ | --------------------------------------------------------------------------------------------------------------------------------------------- |
| `/admin` / `dashboard`                           | Dashboard page                                                                                                                                |
| `airlines`                                       | `airlines` (Airlines resource)                                                                                                                |
| `airports` (+ import/export/fuel)                | `airports` (Airports resource; import/export/bulk-add live in the resource)                                                                   |
| `aircraft` (+ import/export)                     | `subfleets/{id}/aircraft` (Aircraft resource nested under Subfleets)                                                                          |
| `subfleets` (+ expenses/fares/ranks/typeratings) | `subfleets` (Subfleets resource + relation managers)                                                                                          |
| `flights` (+ fares/fields/subleets)              | `flights/{bundle}/flight` (Flight resource nested under FlightBundles; fares/subleets via relation managers)                                  |
| `flightfields`                                   | **no equivalent** — flight field _definitions_ have no admin UI; per-flight field _values_ via the Field values relation manager              |
| `pireps` (+ pending/status/comments)             | `pireps` (Pireps resource)                                                                                                                    |
| `pirepfields`                                    | `pirep-fields` (PirepFields resource)                                                                                                         |
| `userfields`                                     | `user-fields` (UserFields resource)                                                                                                           |
| `expenses`                                       | `expenses` (Expenses resource)                                                                                                                |
| `fares`                                          | `fares` (Fares resource)                                                                                                                      |
| `finances`                                       | `finances` (Finances page)                                                                                                                    |
| `ranks` (+ subfleets)                            | `ranks` (Ranks resource)                                                                                                                      |
| `typeratings` (+ subfleets/users)                | `typeratings` (Typeratings resource)                                                                                                          |
| `airframes` / `sbupdate`                         | `simbrief-airframes` (SimBriefAirframes resource)                                                                                             |
| `users` (+ typeratings/awards/apikey)            | `users` (Users resource)                                                                                                                      |
| `invites`                                        | `invites` (Invites resource)                                                                                                                  |
| `roles`                                          | `roles` (Roles resource)                                                                                                                      |
| `pages`                                          | `pages` (Pages resource)                                                                                                                      |
| `news` (dashboard/news)                          | `news` (News resource)                                                                                                                        |
| `activities`                                     | `activity-logs` (ActivityLogs resource)                                                                                                       |
| `settings`                                       | `settings` (Settings page)                                                                                                                    |
| `maintenance` (+ cache/queue/update/reseed/cron) | `maintenance` (Maintenance page)                                                                                                              |
| `modules`                                        | Addon Manager module page (`/admin/addons` — from `phpvms-addon-manager`)                                                                     |
| `files`                                          | file uploads moved into the resources that own them (news/pages)                                                                              |
| —                                                | NEW: `oauth-clients` (OAuthClients resource), `backups` (Backups page), `routeforge` (RouteForge page), `addon-settings` (AddonSettings page) |

### 3.3 Application code structure

- **Repositories removed**: `prettus/l5-repository` dropped, `app/Repositories`
  and `config/repository.php` deleted. Search/read paths now use Query classes
  (`AirportSearchQuery`, `UserSearchQuery`, `PirepSearchQuery`) and model
  scopes.
- **Enums moved and converted to native PHP enums**:
  `App\Models\Enums\*` → `App\Enums\*`, class-based enum objects → native
  backed enums (e.g. `PirepState: int`). `App\Models\Casts\*` →
  `App\Casts\*`, `App\Models\Observers\*` → `App\Observers\*`,
  `App\Models\Traits\*` → `App\Traits\*`, `Days` → `app/Support/Days.php`.
  `PirepStatus` survives as a `class_alias` of `PirepPhase` (deprecated).
- **Removed models**: `GeoJson`, `Module`, `SimBriefXML`.
- **Removed services**: `AirportLookup` (VaCentral), `AnalyticsService`,
  `DatabaseService`.

### 3.4 Addons / modules

- **Module system replaced**: `nwidart/laravel-modules` +
  `joshbrw/laravel-module-installer` removed; addons are now installed via the
  `phpvms-module` composer type (`oomphinc/composer-installers-extender`),
  registered through `AddonServiceProvider` from the `addons` table + boot
  cache. `module.json` manifest schema is validated (name, alias, providers,
  type, compat, registry_id, version).
- Bundled `Sample` and `Vacentral` modules are deleted from the repo (data
  migration removes the VaCentral module row).
- **VaCentral integration removed**: `nabeel/vacentral` package,
  `config/vacentral.php`, `vacentral_api_key` / `vacentral_api_url` config
  keys gone. Airport data lookups now go through the phpVMS API service
  (`api.phpvms.net`).
- Deep dive deferred to the addon update guide (next).

### 3.5 Roles & permissions

- `laratrust` → `spatie/laravel-permission`: new tables, new APIs
  (`config/permission.php`, `config/roles.php`), seeded roles/permissions via
  data migration. Custom roles/permission checks and any addon calling the
  laratrust API must be rewritten.

### 3.6 Database & migrations

- Migrations moved from `app/Database` to `database/migrations` +
  `database/migrations_data`; **never edit** the historical ones — 8.0 ships
  new data migrations instead.
- **Destructive**: `add_acars_pirep_foreign_key` purges orphaned `acars` rows
  in batches before adding the FK (unreachable rows from 7.x hard-deletes).
  Time scales with orphan count; back up first. Skipped on SQLite.
- **Required action**: `flights:migrate-time-columns` backfills the new TIME
  columns from legacy `dpt_time`/`arr_time`.
- **Renames**: `flights.active` → `flights.enabled`; raw SQL against
  `flights.active` fails. `acars.live_time` split into
  `pireps.tombstone_time` + `livemap.*` settings; `flights.visible` is now
  cron-managed and must not be written by admin code.
- `Flight::active()` scope deprecated → `Flight::visible()`; `Pirep::position()`
  now returns `PirepPosition`; `Pirep::scopeActiveFlights()` gone.
- `FlightResource` keeps `active` only as a deprecated alias of `enabled`.

### 3.7 Removed config files

`config/broadcasting.php`, `compile.php`, `cron.php`, `flare.php`,
`gravatar.php`, `ignition.php`, `importer.php`, `installer.php`,
`languages.php`, `laratrust.php`, `map.php`, `repository.php`,
`self-update.php`, `updater.php`, `vacentral.php`, `view.php`.

What happened to their contents (verified): `languages.php` and
`config/map.php`'s `metar_wms` moved into `config/phpvms.php`;
`broadcasting.php`/`view.php`/`compile.php` were Laravel-11-skeleton removals;
`laratrust.php` → `permission.php` + `roles.php` (spatie);
`repository.php` gone with the repository pattern; `updater.php`/`cron.php`
were near-empty; `importer.php`'s `batch_size` was dropped.

### 3.8 Settings table

The `settings` table and the `setting()` helper are unchanged in concept
(7.x seeded from `app/Database/seeds/settings.yml`; 8.0 seeds from
`SettingsSeeder` + `YamlSeeder`, 90 keys). Verified key changes:

- **Renamed (values carried across by data migration):**
  `acars.live_time` → `pireps.tombstone_time` (see the full table in
  `docs/UPGRADING.md`), `acars.center_coords` / `acars.default_zoom` /
  `acars.update_interval` → `livemap.*`, and the Discord settings
  `notifications.discord_public_webhook_url` /
  `notifications.discord_private_webhook_url` →
  `notifications.discord_public_route` / `notifications.discord_private_route`
  (a route now accepts a webhook URL **or** a channel ID, which requires
  `DISCORD_BOT_TOKEN`).
- **New:** `livemap.live_time` / `livemap.idle_time`,
  `fares.auto_price` / `fares.low_cost_multiplier`, `pilots.only_show_flights_from_current`,
  `registry.public_key` (addon registry), `va_global_id`.

### 3.9 Themes

The `igaster/laravel-theme` system is unchanged: themes live in
`resources/views/layouts/<name>`, default is `seven` (`DEFAULT_THEME` env or
`general.theme` setting), `SetActiveTheme` middleware unchanged. The `beta`
and `default` themes were **removed** — `seven` is the only shipped theme.
The seven layout is essentially identical but now loads assets via
`@vite` (Vite build) instead of webpack/mix, and gained three new views:
`oauth/authorize`, `profile/connections`, `flights/simbrief_username`.

### 3.10 Behavior changes worth noticing

- Live map: prefiled flights visible before first position; completed/paused
  flights linger per configured times; admin edits no longer keep dead flights
  on the map.
- ACARS log entries capped at 1000 chars (incoming log/event strings).
- `flights.days` day-of-week bitmask is **no longer honored by
  `SetVisibleFlights`** — the field itself is not deprecated (still in the
  form, CSV import/export, and `Flight::on_day()`), but in 7.x
  `SetActiveFlights` hid flights on non-scheduled days, and nothing does that
  anymore: day-scheduled flights are now visible every day unless a bundle
  window says otherwise. Audit `flights.days` usage post-upgrade.
- PIREP custom fields are typed — ACARS writes declaring
  `type` (NUMBER/TEXT/TIMESTAMP/BOOLEAN) get `typed_value` casts; rows without
  a type keep plain-string behavior.

---

## 4. Verified answers to the original open questions

All six were checked against the actual diff (`7.0.10` vs `origin/main`), not
the docs:

1. **API endpoints**: surface unchanged except `airports/{id}` →
   `airports/{airport}` (binding rename) and new `POST users/simbrief_username`
   (`settings:write`). All 55 protected routes carry exactly one `scope:`
   middleware; nine scopes total (list in §1.3).
2. **`flights.days`**: not deprecated — still editable and exported; only the
   visibility cron stopped honoring it (§3.10).
3. **Admin URL map**: full 7.x → Filament table in §3.2. One real gap found:
   `admin/flightfields` (flight field _definitions_) has no Filament
   equivalent — worth confirming that's intentional before 8.0 ships.
4. **Laravel 10→13**: bootstrap/app.php replaces the HTTP kernel; env var
   renames and changed driver defaults are the operator-facing items (§3.1).
5. **Themes**: system unchanged; `beta`/`default` themes removed; seven now
   uses Vite (§3.9).
6. **Settings**: same key-value store, ~90 keys; renames are data-migrated
   (incl. the Discord webhook-url → route rename); new keys listed (§3.8).

Still worth a maintainer's eye (behavior, not facts): the `flightfields` UI
gap above, and whether `importer.batch_size`'s removal matters to anyone.

## Next

- Addon/module update guide (module.json, AddonServiceProvider, PSR-4 changes,
  Filament panel integration, registry publishing) — separate document.
- Turn this inventory into the final guide: better-auth style with per-item
  "What to do" sections, an audience-routing table, and an upgrade checklist.

<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\AircraftStatus;
use App\Enums\PirepState;
use App\Enums\UserState;
use App\Models\Aircraft;
use App\Models\Flight;
use App\Models\Pirep;
use App\Models\User;
use App\Support\Metrics\Facades\Metrics;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the first-party airline-wide metric set (page-metrics OpenSpec
 * change, design.md D7).
 *
 * Each resolver below states its predicate explicitly rather than reusing an
 * existing query built for a different purpose: `app/Widgets/LatestPireps.php`
 * excludes only DRAFT/IN_PROGRESS/CANCELLED (an exclusion list, which silently
 * admits every PirepState added later), so it is not reused here even though
 * it is the closest existing PIREP query. `pireps.total` below tests
 * `state === PirepState::ACCEPTED` positively instead.
 *
 * Registered from boot() (not register()) so this provider's own disable-
 * safety matches every addon's: {@see MetricsServiceProvider} makes the
 * `metrics` binding exist before boot() runs.
 *
 * TTL: every metric below uses 5 minutes (300s). All seven are airline-wide
 * "marketing figures" (design.md Risks) -- pilot/flight/PIREP/aircraft
 * counts and hours/distance totals for a landing page -- none of which need a
 * real-time value; a 5-minute lag is not user-visible for any of them. None
 * is flagged with a different TTL because none has a use case where it would
 * be.
 */
final class FirstPartyMetricsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerPilotMetrics();
        $this->registerFlightMetrics();
        $this->registerHoursAndDistanceMetrics();
        $this->registerFleetMetrics();
    }

    /**
     * Pilot population: users can be soft-deleted (`deleted_at`, excluded by
     * Eloquent's default scope), carry `UserState::DELETED`, or be
     * force-deleted (no row at all). `UserService::removeUser()`
     * (app/Services/UserService.php:165-188) sets `state = UserState::DELETED`
     * WITHOUT soft-deleting when the pilot has PIREPs -- only force-deletes
     * when they have none. So soft-delete exclusion alone is not enough:
     * `pilots.total` excludes both, which is every population removeUser()
     * can produce for a account that still has a row.
     */
    private function registerPilotMetrics(): void
    {
        Metrics::registry()->register([
            'id'         => 'pilots.total',
            'label'      => 'Total Pilots',
            'group'      => 'pilots',
            'value_type' => 'integer',
            'format'     => 'integer',
            'ttl'        => 300,
            'resolve'    => static fn (): int => User::query()
                ->where('state', '!=', UserState::DELETED)
                ->count(),
        ]);
    }

    /**
     * `flights.total`: "route count" resolved (tasks.md 4.5a) as enabled,
     * visible flight legs -- phpVMS has no `routes` table; `flights`
     * (database/migrations/2025_01_13_003704_create_phpvms_table.php:381-422)
     * is the nearest thing, and each row is a published flight-number leg, not
     * a distinct airport pair. Labelled "Flights", not "Routes", because it
     * counts every published leg row. The original `active` boolean
     * (:411) was renamed to `enabled` by
     * database/migrations/2026_05_19_100002_add_bundle_columns_to_flights_table.php:22
     * -- `Flight` (app/Models/Flight.php:198,251) only exposes `enabled`, and
     * that migration builds a `(bundle_id, enabled, visible)` index
     * specifically for this "is this flight live" predicate. `visible` is
     * unchanged (:412); the model's default SoftDeletes scope excludes
     * trashed rows.
     *
     * `pireps.total`: PIREPs actually flown and accepted. Tests
     * `state === PirepState::ACCEPTED` positively rather than excluding a
     * list, per design.md D7 -- an exclusion list silently admits every state
     * added later (PENDING, REJECTED, PAUSED, DELETED all remain uncounted
     * here, matching app/Enums/PirepState.php:13-20).
     */
    private function registerFlightMetrics(): void
    {
        Metrics::registry()->register([
            'id'         => 'flights.total',
            'label'      => 'Flights',
            'group'      => 'flights',
            'value_type' => 'integer',
            'format'     => 'integer',
            'ttl'        => 300,
            'resolve'    => static fn (): int => Flight::query()
                ->where('enabled', true)
                ->where('visible', true)
                ->count(),
        ]);

        Metrics::registry()->register([
            'id'         => 'pireps.total',
            'label'      => 'Flights Completed',
            'group'      => 'flights',
            'value_type' => 'integer',
            'format'     => 'integer',
            'ttl'        => 300,
            'resolve'    => static fn (): int => Pirep::query()
                ->where('state', PirepState::ACCEPTED)
                ->count(),
        ]);
    }

    /**
     * `hours.total`: sum of `flight_time` (minutes -- confirmed against
     * app/Filament/Widgets/BlockHoursStatWidget.php, which divides the same
     * column by 60 to display hours) over ACCEPTED PIREPs only, converted to
     * hours and rounded to 1 decimal place, matching that widget's rounding.
     * Eloquent's `sum()` already returns `0` (never null) over zero matching
     * rows, so an airline with no accepted PIREPs yet resolves to `0.0`, not
     * null (null is reserved for a failed resolution, design.md D5).
     *
     * `distance.total`: sum of `distance` over ACCEPTED PIREPs, in the
     * application's internal distance unit (`config('phpvms.internal_units.distance')`
     * = nautical miles, config/phpvms.php:119). The `distance` column carries
     * a `DistanceCast` on the Eloquent model, but `sum()` is a query-builder
     * aggregate operating on the raw column, so no cast is applied here --
     * matching the existing `DistanceFlownChart` widget's convention of
     * summing the raw decimal and rounding to the nearest whole unit.
     */
    private function registerHoursAndDistanceMetrics(): void
    {
        Metrics::registry()->register([
            'id'         => 'hours.total',
            'label'      => 'Hours Flown',
            'group'      => 'flights',
            'value_type' => 'float',
            'format'     => 'decimal_1',
            'ttl'        => 300,
            'resolve'    => static function (): float {
                $minutes = (float) Pirep::query()->where('state', PirepState::ACCEPTED)->sum('flight_time');

                return round($minutes / 60, 1);
            },
        ]);

        Metrics::registry()->register([
            'id'         => 'distance.total',
            'label'      => 'Distance Flown (nmi)',
            'group'      => 'flights',
            'value_type' => 'integer',
            'format'     => 'integer',
            'ttl'        => 300,
            'resolve'    => static function (): int {
                $distance = (float) Pirep::query()->where('state', PirepState::ACCEPTED)->sum('distance');

                return (int) round($distance);
            },
        ]);
    }

    /**
     * `aircraft.total`: every aircraft row, excluding soft-deleted
     * (`app/Models/Aircraft.php:126` uses `SoftDeletes`; the default query
     * scope already excludes trashed rows).
     *
     * `aircraft.active`: aircraft whose `status` column (an `AircraftStatus`,
     * default `'A'`) is `ACTIVE` -- deliberately the `status` column, not the
     * separate `state` tinyint column, matching
     * `app/Filament/Widgets/TailsAvailableStatWidget.php`'s existing
     * "active of total" predicate. `status` and soft-delete are independent:
     * an aircraft can be soft-deleted regardless of its status, and this
     * metric inherits the same default soft-delete exclusion as
     * `aircraft.total`.
     */
    private function registerFleetMetrics(): void
    {
        Metrics::registry()->register([
            'id'         => 'aircraft.total',
            'label'      => 'Total Aircraft',
            'group'      => 'fleet',
            'value_type' => 'integer',
            'format'     => 'integer',
            'ttl'        => 300,
            'resolve'    => static fn (): int => Aircraft::query()->count(),
        ]);

        Metrics::registry()->register([
            'id'         => 'aircraft.active',
            'label'      => 'Active Aircraft',
            'group'      => 'fleet',
            'value_type' => 'integer',
            'format'     => 'integer',
            'ttl'        => 300,
            'resolve'    => static fn (): int => Aircraft::query()
                ->where('status', AircraftStatus::ACTIVE)
                ->count(),
        ]);
    }
}

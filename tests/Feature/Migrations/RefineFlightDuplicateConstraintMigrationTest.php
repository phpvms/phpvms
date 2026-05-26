<?php

declare(strict_types=1);

use App\Models\Airline;
use App\Models\Airport;
use App\Models\Flight;
use App\Models\FlightBundle;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Activitylog\Models\Activity;

/*
 * The migration runs once at test setup (via RefreshDatabase). To exercise its
 * up() logic with pre-existing violating rows, each test:
 *   1. Calls $migration->down() to drop _dup_key + unique index
 *   2. Seeds the desired violating rows directly via DB::table (bypasses the
 *      Flight model's mutators so we can write '', '0', or duplicates)
 *   3. Calls $migration->up() and asserts the resulting state
 */

beforeEach(function (): void {
    $this->migration = require database_path('migrations/2026_05_26_031902_refine_flight_duplicate_constraint.php');

    // Drop the constraint + column so the test can seed violating rows.
    // The migration's down() handles both.
    $this->migration->down();

    // Fresh FK targets per test — RefreshDatabase wipes the DB between tests
    // so static state on the helper would point to gone-away rows.
    $this->airlineId = Airline::factory()->create()->id;
    $this->bundleId = FlightBundle::factory()->create()->id;
    $this->airportId = Airport::factory()->create()->id;
    $this->idSeq = 100000;

    /**
     * Build a flight row tuple bound to this test's FK targets.
     *
     * @param  array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    $this->flightTuple = function (array $overrides = []): array {
        $now = now()->toDateTimeString();

        return array_merge([
            'id'                   => (string) (++$this->idSeq),
            'airline_id'           => $this->airlineId,
            'bundle_id'            => $this->bundleId,
            'flight_number'        => 100,
            'route_code'           => null,
            'route_leg'            => null,
            'dpt_airport_id'       => $this->airportId,
            'arr_airport_id'       => $this->airportId,
            'alt_airport_id'       => null,
            'distance'             => 100,
            'flight_time'          => 60,
            'departure_time'       => '08:00:00',
            'arrival_time'         => '09:00:00',
            'load_factor'          => 50,
            'load_factor_variance' => 0,
            'has_bid'              => false,
            'enabled'              => true,
            'visible'              => true,
            'days'                 => 0,
            'level'                => 0,
            'owner_type'           => null,
            'owner_id'             => null,
            'created_at'           => $now,
            'updated_at'           => $now,
        ], $overrides);
    };
});

it('canonicalizes route_code = "" to NULL during the cleanup pass', function (): void {
    DB::table('flights')->insert(($this->flightTuple)(['flight_number' => 200, 'route_code' => '']));
    DB::table('flights')->insert(($this->flightTuple)(['flight_number' => 201, 'route_code' => '0']));
    DB::table('flights')->insert(($this->flightTuple)(['flight_number' => 202, 'route_code' => 'AB']));

    $this->migration->up();

    expect(DB::table('flights')->where('flight_number', 200)->value('route_code'))->toBeNull()
        ->and(DB::table('flights')->where('flight_number', 201)->value('route_code'))->toBeNull()
        ->and(DB::table('flights')->where('flight_number', 202)->value('route_code'))->toBe('AB');
});

it('canonicalizes route_leg = "" / "0" to NULL during the cleanup pass', function (): void {
    DB::table('flights')->insert(($this->flightTuple)(['flight_number' => 300, 'route_leg' => '']));
    DB::table('flights')->insert(($this->flightTuple)(['flight_number' => 301, 'route_leg' => '0']));
    DB::table('flights')->insert(($this->flightTuple)(['flight_number' => 302, 'route_leg' => 5]));

    $this->migration->up();

    expect(DB::table('flights')->where('flight_number', 300)->value('route_leg'))->toBeNull()
        ->and(DB::table('flights')->where('flight_number', 301)->value('route_leg'))->toBeNull()
        ->and(DB::table('flights')->where('flight_number', 302)->value('route_leg'))->toEqual(5);
});

it('keeps the lowest id and disables higher-id duplicates in each cluster', function (): void {
    // Two enabled, non-owner flights sharing the 5-tuple: lowest id wins.
    DB::table('flights')->insert(($this->flightTuple)(['id' => '500001', 'flight_number' => 100, 'enabled' => true]));
    DB::table('flights')->insert(($this->flightTuple)(['id' => '500002', 'flight_number' => 100, 'enabled' => true]));
    DB::table('flights')->insert(($this->flightTuple)(['id' => '500003', 'flight_number' => 100, 'enabled' => true]));

    // A different cluster (different flight_number) — should be untouched.
    DB::table('flights')->insert(($this->flightTuple)(['id' => '500099', 'flight_number' => 101, 'enabled' => true]));

    $this->migration->up();

    expect(DB::table('flights')->where('id', '500001')->value('enabled'))->toBeTruthy()
        ->and(DB::table('flights')->where('id', '500002')->value('enabled'))->toBeFalsy()
        ->and(DB::table('flights')->where('id', '500003')->value('enabled'))->toBeFalsy()
        ->and(DB::table('flights')->where('id', '500099')->value('enabled'))->toBeTruthy();
});

it('writes an activity-log entry for each auto-disabled flight', function (): void {
    DB::table('flights')->insert(($this->flightTuple)(['id' => '600001', 'flight_number' => 100]));
    DB::table('flights')->insert(($this->flightTuple)(['id' => '600002', 'flight_number' => 100]));

    $this->migration->up();

    $logEntry = Activity::query()
        ->where('subject_type', Flight::class)
        ->where('subject_id', '600002')
        ->orderByDesc('id')
        ->first();

    expect($logEntry)->not->toBeNull()
        ->and($logEntry->description)->toContain('Auto-disabled by RouteForge dedup migration')
        ->and($logEntry->description)->toContain('600001')
        ->and($logEntry->properties->get('kept_flight_id'))->toBe('600001');
});

it('does NOT disable pre-existing disabled duplicates', function (): void {
    DB::table('flights')->insert(($this->flightTuple)(['id' => '700001', 'flight_number' => 100, 'enabled' => true]));
    DB::table('flights')->insert(($this->flightTuple)(['id' => '700002', 'flight_number' => 100, 'enabled' => false]));

    $this->migration->up();

    expect(DB::table('flights')->where('id', '700001')->value('enabled'))->toBeTruthy()
        ->and(DB::table('flights')->where('id', '700002')->value('enabled'))->toBeFalsy();
});

it('does NOT disable owner-typed duplicates', function (): void {
    DB::table('flights')->insert(($this->flightTuple)(['id' => '800001', 'flight_number' => 100, 'owner_type' => null]));
    DB::table('flights')->insert(($this->flightTuple)(['id' => '800002', 'flight_number' => 100, 'owner_type' => User::class, 'owner_id' => 1]));

    $this->migration->up();

    // The owner-typed row stays enabled; it has a separate namespace.
    expect(DB::table('flights')->where('id', '800001')->value('enabled'))->toBeTruthy()
        ->and(DB::table('flights')->where('id', '800002')->value('enabled'))->toBeTruthy();
});

it('blocks INSERT of an enabled, non-owner duplicate after the constraint is applied', function (): void {
    $this->migration->up();

    DB::table('flights')->insert(($this->flightTuple)(['id' => '900001', 'flight_number' => 100]));

    expect(fn () => DB::table('flights')->insert(($this->flightTuple)(['id' => '900002', 'flight_number' => 100])))
        ->toThrow(QueryException::class);
});

it('allows INSERT of a disabled duplicate after the constraint is applied', function (): void {
    $this->migration->up();

    DB::table('flights')->insert(($this->flightTuple)(['id' => '910001', 'flight_number' => 100, 'enabled' => true]));

    // Same 5-tuple but enabled = false → _dup_key is NULL → no UNIQUE collision
    DB::table('flights')->insert(($this->flightTuple)(['id' => '910002', 'flight_number' => 100, 'enabled' => false]));

    expect(DB::table('flights')->whereIn('id', ['910001', '910002'])->count())->toBe(2);
});

it('allows INSERT of an owner-typed duplicate after the constraint is applied', function (): void {
    $this->migration->up();

    DB::table('flights')->insert(($this->flightTuple)(['id' => '920001', 'flight_number' => 100, 'owner_type' => null]));
    DB::table('flights')->insert(($this->flightTuple)(['id' => '920002', 'flight_number' => 100, 'owner_type' => User::class, 'owner_id' => 1]));

    expect(DB::table('flights')->whereIn('id', ['920001', '920002'])->count())->toBe(2);
});

it('creates the _dup_key column and unique index', function (): void {
    $this->migration->up();

    expect(Schema::hasColumn('flights', '_dup_key'))->toBeTrue();

    // Verify the unique index exists. Schema::getIndexes is Laravel 11+;
    // fall back to a duplicate-insert probe if unavailable.
    if (method_exists(Schema::class, 'getIndexes')) {
        $indexes = Schema::getIndexes('flights');
        $names = array_map(static fn (array $i) => $i['name'], $indexes);
        expect($names)->toContain('flights_dup_key_unique');
    }
});

it('rolls back cleanly via down()', function (): void {
    $this->migration->up();

    expect(Schema::hasColumn('flights', '_dup_key'))->toBeTrue();

    $this->migration->down();

    expect(Schema::hasColumn('flights', '_dup_key'))->toBeFalse();
});

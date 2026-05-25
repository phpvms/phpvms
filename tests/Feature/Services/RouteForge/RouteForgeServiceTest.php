<?php

declare(strict_types=1);

use App\Models\Airline;
use App\Models\Airport;
use App\Models\Fare;
use App\Models\Flight;
use App\Models\FlightBundle;
use App\Models\Subfleet;
use App\Services\RouteForge\CommitInput;
use App\Services\RouteForge\Exceptions\LintFailedException;
use App\Services\RouteForge\LintRunner;
use App\Services\RouteForge\RouteForgeService;
use Illuminate\Support\Collection;

/*
 * Full RouteForgeService::commit pipeline coverage. Hits DB via SQLite
 * in-memory through RefreshDatabase (Pest.php global setup).
 *
 * Activity logging is disabled globally in AppServiceProvider::boot() and
 * normally re-enabled per-request by the EnableActivityLogging middleware.
 * Service tests don't go through HTTP, so beforeEach() enables it manually —
 * mirrors the production path where every route hitting RouteForgeService
 * has already passed through the middleware.
 */
beforeEach(function (): void {
    activity()->enableLogging();
});

function makeCommitInput(
    Airline $airline,
    Subfleet $subfleet,
    array $rows,
    ?FlightBundle $existing = null,
    ?string $fareMultiplier = null,
): CommitInput {
    $bundle = $existing instanceof FlightBundle
        ? new FlightBundle([
            'name'        => $existing->name,
            'description' => $existing->description,
            'enabled'     => $existing->enabled,
            'start_date'  => $existing->start_date,
            'end_date'    => $existing->end_date,
        ])
        : new FlightBundle([
            'name'    => 'RouteForge Test Bundle',
            'enabled' => true,
        ]);
    $bundle->created_by = null;

    return new CommitInput(
        bundle: $bundle,
        existingBundle: $existing,
        rows: $rows,
        airline: $airline,
        selectedSubfleets: new Collection([$subfleet->loadMissing(['aircraft', 'fares'])]),
        event: null,
        subfleetIds: [$subfleet->id],
        fareMultiplier: $fareMultiplier,
        flightType: null,
        airlineStats: [
            'existing_active_flights_count' => 0,
            'hub_airports'                  => [],
            'home_airport'                  => null,
        ],
    );
}

function svcRow(int $flightNumber, Airline $airline, string $dpt, string $arr): array
{
    return [
        'airline_id'     => $airline->id,
        'flight_number'  => $flightNumber,
        'route_code'     => null,
        'route_leg'      => null,
        'dpt_airport_id' => $dpt,
        'arr_airport_id' => $arr,
        'dpt_timezone'   => 'America/Los_Angeles',
        'arr_timezone'   => 'America/New_York',
        'distance_nm'    => 2570,
        'flight_time'    => 330,
    ];
}

function svc(): RouteForgeService
{
    return new RouteForgeService(LintRunner::defaults());
}

it('commits a happy-path batch creating bundle + flights + subfleet pivots + one activity log entry', function (): void {
    $airline = Airline::factory()->create();
    $subfleet = Subfleet::factory()->create(['airline_id' => $airline->id]);
    $sfo = Airport::factory()->create();
    $jfk = Airport::factory()->create();

    $input = makeCommitInput($airline, $subfleet, [
        svcRow(100, $airline, $sfo->id, $jfk->id),
        svcRow(101, $airline, $jfk->id, $sfo->id),
    ]);

    $bundleCountBefore = FlightBundle::query()->count();
    $flightCountBefore = Flight::query()->count();

    $result = svc()->commit($input);

    expect($result->createdCount)->toBe(2)
        ->and($result->flightIds)->toHaveCount(2)
        ->and($result->batchId)->toMatch('/^[0-9A-HJKMNP-TV-Z]{26}$/i') // ULID
        ->and(FlightBundle::query()->count())->toBe($bundleCountBefore + 1)
        ->and(Flight::query()->count())->toBe($flightCountBefore + 2);

    $bundle = FlightBundle::query()->find($result->bundleId);
    $flights = Flight::query()->with('subfleets')->whereIn('id', $result->flightIds)->get();

    expect($bundle->name)->toBe('RouteForge Test Bundle');
    foreach ($flights as $flight) {
        expect((int) $flight->bundle_id)->toBe($result->bundleId)
            ->and($flight->subfleets->pluck('id')->all())->toContain($subfleet->id);
    }

    // Single activity log entry on the bundle. Use DB::table to bypass
    // any Spatie-side scoping. subject_type may use morph alias OR the
    // FQCN depending on Laravel's enforceMorphMap state; assert by
    // subject_id + log_name only.
    $activityCount = DB::table('activity_log')
        ->where('log_name', 'routeforge')
        ->where('subject_id', $result->bundleId)
        ->count();

    expect($activityCount)->toBe(1);
});

it('throws LintFailedException and rolls back when rows trigger an error rule (L6)', function (): void {
    $airline = Airline::factory()->create();
    $subfleet = Subfleet::factory()->create(['airline_id' => $airline->id]);
    $sfo = Airport::factory()->create();

    $input = makeCommitInput($airline, $subfleet, [
        // L6: origin == destination.
        svcRow(100, $airline, $sfo->id, $sfo->id),
    ]);

    $bundleCountBefore = FlightBundle::query()->count();
    $flightCountBefore = Flight::query()->count();

    $caught = null;
    try {
        svc()->commit($input);
    } catch (LintFailedException $lintFailedException) {
        $caught = $lintFailedException;
    }

    expect($caught)->toBeInstanceOf(LintFailedException::class)
        ->and($caught->report->errors->first()->ruleId)->toBe('L6')
        ->and(FlightBundle::query()->count())->toBe($bundleCountBefore)
        ->and(Flight::query()->count())->toBe($flightCountBefore);
});

it('appends to an existing bundle without persisting a new one in attach-existing mode', function (): void {
    $airline = Airline::factory()->create();
    $subfleet = Subfleet::factory()->create(['airline_id' => $airline->id]);
    $sfo = Airport::factory()->create();
    $jfk = Airport::factory()->create();
    $existing = FlightBundle::factory()->create([
        'name'        => 'Existing Bundle',
        'description' => 'Pre-existing',
        'enabled'     => true,
    ]);

    $input = makeCommitInput($airline, $subfleet, [
        svcRow(200, $airline, $sfo->id, $jfk->id),
        svcRow(201, $airline, $jfk->id, $sfo->id),
    ], existing: $existing);

    $bundleCountBefore = FlightBundle::query()->count();

    $result = svc()->commit($input);

    expect($result->bundleId)->toBe($existing->id)
        ->and($result->createdCount)->toBe(2)
        // No new bundle row created.
        ->and(FlightBundle::query()->count())->toBe($bundleCountBefore);

    // Existing bundle's columns untouched.
    $existing->refresh();
    expect($existing->name)->toBe('Existing Bundle')
        ->and($existing->description)->toBe('Pre-existing');

    // Activity log entry on the existing bundle with appended_to_existing flag.
    $row = DB::table('activity_log')
        ->where('log_name', 'routeforge')
        ->where('subject_id', $existing->id)
        ->latest('id')
        ->first();

    expect($row)->not->toBeNull();
    $properties = json_decode((string) $row->properties, true);
    expect($properties['appended_to_existing'] ?? null)->toBeTrue()
        ->and($properties['count'] ?? null)->toBe(2);
});

it('stamps the fare multiplier verbatim onto flight_fare pivot rows for each subfleet fare', function (): void {
    $airline = Airline::factory()->create();
    $subfleet = Subfleet::factory()->create(['airline_id' => $airline->id]);

    // Attach 2 fares to the subfleet.
    $fares = Fare::factory()->count(2)->create();
    foreach ($fares as $fare) {
        $subfleet->fares()->attach($fare->id);
    }

    $sfo = Airport::factory()->create();
    $jfk = Airport::factory()->create();

    $input = makeCommitInput($airline, $subfleet, [
        svcRow(100, $airline, $sfo->id, $jfk->id),
        svcRow(101, $airline, $jfk->id, $sfo->id),
    ], fareMultiplier: '+10%');

    $result = svc()->commit($input);

    // 2 flights × 2 fares = 4 flight_fare rows, all priced "+10%".
    $rows = DB::table('flight_fare')
        ->whereIn('flight_id', $result->flightIds)
        ->get();

    expect($rows)->toHaveCount(4);
    foreach ($rows as $row) {
        expect($row->price)->toBe('+10%');
    }
});

it('routes departure_time/arrival_time payload keys to the structured Flight columns (legacy strings stay NULL)', function (): void {
    $airline = Airline::factory()->create();
    $subfleet = Subfleet::factory()->create(['airline_id' => $airline->id]);
    $sfo = Airport::factory()->create();
    $jfk = Airport::factory()->create();

    $row = svcRow(100, $airline, $sfo->id, $jfk->id);
    $row['departure_time'] = '08:30';
    $row['arrival_time'] = '17:15';

    $input = makeCommitInput($airline, $subfleet, [$row]);

    $result = svc()->commit($input);

    /** @var Flight $flight */
    $flight = Flight::query()->findOrFail($result->flightIds[0]);

    // Modern structured columns populated via Laravel datetime cast.
    expect($flight->departure_time)->not->toBeNull()
        ->and($flight->departure_time->format('H:i'))->toBe('08:30')
        ->and($flight->arrival_time)->not->toBeNull()
        ->and($flight->arrival_time->format('H:i'))->toBe('17:15')
        // Legacy free-form columns NOT populated by RouteForge (Decision: new
        // flights write only modern columns; FlightResource synthesizes the
        // legacy keys on-the-wire from the structured values).
        ->and($flight->dpt_time)->toBeNull()
        ->and($flight->arr_time)->toBeNull();
});

it('suppresses per-flight activity log entries via withoutEvents', function (): void {
    $airline = Airline::factory()->create();
    $subfleet = Subfleet::factory()->create(['airline_id' => $airline->id]);
    $sfo = Airport::factory()->create();
    $jfk = Airport::factory()->create();

    $input = makeCommitInput($airline, $subfleet, [
        svcRow(100, $airline, $sfo->id, $jfk->id),
        svcRow(101, $airline, $jfk->id, $sfo->id),
        svcRow(102, $airline, $sfo->id, $jfk->id),
    ]);

    $result = svc()->commit($input);

    // Per-flight Spatie ActivityLog entries would set subject_type = Flight;
    // we only want ONE entry on the bundle, none on individual flights.
    $perFlightActivity = DB::table('activity_log')
        ->where('subject_type', Flight::class)
        ->whereIn('subject_id', $result->flightIds)
        ->count();

    expect($perFlightActivity)->toBe(0);
});

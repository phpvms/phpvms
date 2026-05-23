<?php

declare(strict_types=1);

use App\Cron\Nightly\SetVisibleFlights;
use App\Models\Flight;
use App\Models\FlightBundle;
use Carbon\Carbon;

it('hides disabled flight regardless of dates', function (): void {
    $bundle = FlightBundle::factory()->create(['enabled' => true]);
    $flight = Flight::factory()->create([
        'bundle_id'  => $bundle->id,
        'enabled'    => false,
        'start_date' => Carbon::now('UTC')->subDay(),
        'end_date'   => Carbon::now('UTC')->addDay(),
    ]);

    SetVisibleFlights::run();

    expect($flight->fresh()->visible)->toBeFalse();
});

it('hides enabled flight in disabled bundle', function (): void {
    $bundle = FlightBundle::factory()->create(['enabled' => false]);
    $flight = Flight::factory()->create([
        'bundle_id' => $bundle->id,
        'enabled'   => true,
    ]);

    SetVisibleFlights::run();

    expect($flight->fresh()->visible)->toBeFalse();
});

it('hides enabled flight in a soft-deleted bundle', function (): void {
    $bundle = FlightBundle::factory()->create(['enabled' => true]);
    $flight = Flight::factory()->create([
        'bundle_id' => $bundle->id,
        'enabled'   => true,
    ]);

    $bundle->delete();

    SetVisibleFlights::run();

    expect($flight->fresh()->visible)->toBeFalse();
});

it('uses bundle window when bundle has dates and window expired', function (): void {
    $bundle = FlightBundle::factory()->create([
        'enabled'    => true,
        'start_date' => Carbon::now('UTC')->subDays(10),
        'end_date'   => Carbon::now('UTC')->subDay(),
    ]);
    $flight = Flight::factory()->create([
        'bundle_id'  => $bundle->id,
        'enabled'    => true,
        'start_date' => Carbon::now('UTC')->subDay(),
        'end_date'   => Carbon::now('UTC')->addDay(),
    ]);

    SetVisibleFlights::run();

    expect($flight->fresh()->visible)->toBeFalse();
});

it('uses flight window when bundle has no dates', function (): void {
    $bundle = FlightBundle::factory()->create([
        'enabled'    => true,
        'start_date' => null,
        'end_date'   => null,
    ]);
    $flight = Flight::factory()->create([
        'bundle_id'  => $bundle->id,
        'enabled'    => true,
        'start_date' => Carbon::now('UTC')->subDay(),
        'end_date'   => Carbon::now('UTC')->addDay(),
    ]);

    SetVisibleFlights::run();

    expect($flight->fresh()->visible)->toBeTrue();
});

it('marks bundle visible=false when bundle window expired', function (): void {
    $bundle = FlightBundle::factory()->create([
        'enabled'    => true,
        'start_date' => Carbon::now('UTC')->subDays(10),
        'end_date'   => Carbon::now('UTC')->subDay(),
    ]);

    SetVisibleFlights::run();

    expect($bundle->fresh()->visible)->toBeFalse();
});

it('runForBundle only updates flights in the target bundle', function (): void {
    $bundleA = FlightBundle::factory()->create(['enabled' => true]);
    $bundleB = FlightBundle::factory()->create(['enabled' => false]);

    $flightA = Flight::factory()->create([
        'bundle_id' => $bundleA->id,
        'enabled'   => true,
    ]);
    $flightB = Flight::factory()->create([
        'bundle_id' => $bundleB->id,
        'enabled'   => true,
    ]);

    // First a global pass so we know the initial state.
    SetVisibleFlights::run();
    expect($flightA->fresh()->visible)->toBeTrue()
        ->and($flightB->fresh()->visible)->toBeFalse();

    // Now flip bundle A to disabled and only recompute that bundle.
    $bundleA->enabled = false;
    $bundleA->saveQuietly(); // skip observer's job dispatch in the test
    SetVisibleFlights::runForBundle($bundleA->fresh());

    expect($flightA->fresh()->visible)->toBeFalse()
        ->and($flightB->fresh()->visible)->toBeFalse(); // unchanged from baseline
});

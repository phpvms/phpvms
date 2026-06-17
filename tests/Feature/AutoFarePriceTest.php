<?php

use App\Models\Airline;
use App\Models\Airport;
use App\Models\Fare;
use App\Models\Pirep;
use App\Models\PirepFare;
use App\Models\Subfleet;
use App\Services\FareService;
use App\Services\GeoService;

beforeEach(function (): void {
    loadYamlIntoDb('fleet');
});

/**
 * Build a persisted PIREP whose departure/arrival airports have no usable
 * coordinates, forcing the auto-price distance to fall back to the PIREP's
 * stored `distance` (in nautical miles) for deterministic formula assertions.
 */
function pirepWithStoredDistance(float $distance_nm, bool $low_cost = false): Pirep
{
    $airline = Airline::factory()->create(['low_cost' => $low_cost]);
    $dpt = Airport::factory()->create(['lat' => null, 'lon' => null]);
    $arr = Airport::factory()->create(['lat' => null, 'lon' => null]);

    return Pirep::factory()->create([
        'airline_id'     => $airline->id,
        'dpt_airport_id' => $dpt->id,
        'arr_airport_id' => $arr->id,
        'distance'       => $distance_nm,
    ]);
}

test('airportDistance returns great-circle nautical miles', function (): void {
    // One degree of latitude along a meridian is ~60 nm.
    $from = Airport::factory()->create(['lat' => 0, 'lon' => 0]);
    $to = Airport::factory()->create(['lat' => 1, 'lon' => 0]);

    $nm = app(GeoService::class)->airportDistance($from, $to);

    expect($nm)->toBeGreaterThan(59.0)->toBeLessThan(61.0);
});

test('airportDistance returns null when coordinates are missing', function (): void {
    $from = Airport::factory()->create(['lat' => null, 'lon' => null]);
    $to = Airport::factory()->create(['lat' => 10, 'lon' => 10]);

    expect(app(GeoService::class)->airportDistance($from, $to))->toBeNull();
});

test('auto price uses the base + per-nm formula', function (): void {
    $pirep = pirepWithStoredDistance(1000);
    $fare = Fare::factory()->create([
        'base_price' => 10,
        'per_nm'     => 0.11,
        'multiplier' => 1,
    ]);

    // (10 + 1000 * 0.11) * 1 * 1
    expect(app(FareService::class)->getAutoPrice($pirep, $fare))->toEqual(120.0);
});

test('auto price applies the seat-category multiplier', function (): void {
    $pirep = pirepWithStoredDistance(1000);
    $fare = Fare::factory()->create([
        'base_price' => 10,
        'per_nm'     => 0.11,
        'multiplier' => 3,
    ]);

    // (10 + 1000 * 0.11) * 3
    expect(app(FareService::class)->getAutoPrice($pirep, $fare))->toEqual(360.0);
});

test('auto price applies the low-cost multiplier for low-cost airlines', function (): void {
    updateSetting('fares.low_cost_multiplier', 0.8);

    $pirep = pirepWithStoredDistance(1000, low_cost: true);
    $fare = Fare::factory()->create([
        'base_price' => 10,
        'per_nm'     => 0.11,
        'multiplier' => 1,
    ]);

    // (10 + 1000 * 0.11) * 1 * 0.8
    expect(app(FareService::class)->getAutoPrice($pirep, $fare))->toEqual(96.0);
});

test('auto price does not apply the low-cost multiplier for full-service airlines', function (): void {
    updateSetting('fares.low_cost_multiplier', 0.8);

    $pirep = pirepWithStoredDistance(1000, low_cost: false);
    $fare = Fare::factory()->create([
        'base_price' => 10,
        'per_nm'     => 0.11,
        'multiplier' => 1,
    ]);

    expect(app(FareService::class)->getAutoPrice($pirep, $fare))->toEqual(120.0);
});

test('auto price clamps negative results to zero', function (): void {
    $pirep = pirepWithStoredDistance(1000);
    $fare = Fare::factory()->create();
    // Force a negative input in memory (DB columns are unsigned).
    $fare->base_price = 0;
    $fare->per_nm = -5;
    $fare->multiplier = 1;

    expect(app(FareService::class)->getAutoPrice($pirep, $fare))->toEqual(0.0);
});

test('auto price prefers great-circle distance over the stored distance', function (): void {
    $airline = Airline::factory()->create(['low_cost' => false]);
    // ~60 nm apart, but a wildly different stored distance.
    $dpt = Airport::factory()->create(['lat' => 0, 'lon' => 0]);
    $arr = Airport::factory()->create(['lat' => 1, 'lon' => 0]);

    $pirep = Pirep::factory()->create([
        'airline_id'     => $airline->id,
        'dpt_airport_id' => $dpt->id,
        'arr_airport_id' => $arr->id,
        'distance'       => 9999,
    ]);

    // base 0, per_nm 1, multiplier 1 => price == distance_nm (~60, not 9999)
    $fare = Fare::factory()->create(['base_price' => 0, 'per_nm' => 1, 'multiplier' => 1]);

    expect(app(FareService::class)->getAutoPrice($pirep, $fare))
        ->toBeGreaterThan(59.0)
        ->toBeLessThan(61.0);
});

test('auto price works for a free flight (no linked flight)', function (): void {
    $airline = Airline::factory()->create(['low_cost' => false]);
    $dpt = Airport::factory()->create(['lat' => 0, 'lon' => 0]);
    $arr = Airport::factory()->create(['lat' => 1, 'lon' => 0]);

    $pirep = Pirep::factory()->create([
        'airline_id'     => $airline->id,
        'flight_id'      => null,
        'dpt_airport_id' => $dpt->id,
        'arr_airport_id' => $arr->id,
    ]);

    $fare = Fare::factory()->create(['base_price' => 0, 'per_nm' => 1, 'multiplier' => 1]);

    expect(app(FareService::class)->getAutoPrice($pirep, $fare))
        ->toBeGreaterThan(59.0)
        ->toBeLessThan(61.0);
});

test('subfleet override of an auto-price input takes precedence', function (): void {
    $subfleet = Subfleet::factory()->create();
    $fare = Fare::factory()->create(['base_price' => 10, 'per_nm' => 0.11, 'multiplier' => 1]);

    $fareSvc = app(FareService::class);
    $fareSvc->setForSubfleet($subfleet, $fare, ['per_nm' => 0.20]);

    $reconciled = $fareSvc->getForSubfleet($subfleet)->first();

    expect((float) $reconciled->per_nm)->toEqual(0.20)
        ->and((float) $reconciled->base_price)->toEqual(10.0);
});

test('reconciled fare falls back to its own value without a subfleet override', function (): void {
    $subfleet = Subfleet::factory()->create();
    $fare = Fare::factory()->create(['base_price' => 10, 'per_nm' => 0.11, 'multiplier' => 2]);

    $fareSvc = app(FareService::class);
    $fareSvc->setForSubfleet($subfleet, $fare);

    $reconciled = $fareSvc->getForSubfleet($subfleet)->first();

    expect((float) $reconciled->multiplier)->toEqual(2.0)
        ->and((float) $reconciled->per_nm)->toEqual(0.11);
});

/**
 * Attach a fare to a fresh subfleet, build a PIREP on that subfleet (airports
 * with no coords so distance falls back to 1000 nm), and run it through
 * FareService::saveToPirep().
 */
function saveFareToPirep(): array
{
    $subfleet = Subfleet::factory()->hasAircraft(1)->create();
    $airline = Airline::factory()->create(['low_cost' => false]);
    $dpt = Airport::factory()->create(['lat' => null, 'lon' => null]);
    $arr = Airport::factory()->create(['lat' => null, 'lon' => null]);

    $fare = Fare::factory()->create([
        'price'      => 999,
        'base_price' => 10,
        'per_nm'     => 0.11,
        'multiplier' => 1,
    ]);
    app(FareService::class)->setForSubfleet($subfleet, $fare);

    $pirep = Pirep::factory()->create([
        'airline_id'     => $airline->id,
        'flight_id'      => null,
        'aircraft_id'    => $subfleet->aircraft->first()->id,
        'dpt_airport_id' => $dpt->id,
        'arr_airport_id' => $arr->id,
        'distance'       => 1000,
    ]);

    $pirepFare = new PirepFare(['fare_id' => $fare->id, 'count' => 5]);
    app(FareService::class)->saveToPirep($pirep, [$pirepFare]);

    return [$pirep, PirepFare::where('pirep_id', $pirep->id)->first()];
}

test('saveToPirep keeps the configured price when auto pricing is disabled', function (): void {
    updateSetting('fares.auto_price', false);

    [, $saved] = saveFareToPirep();

    expect((float) $saved->price)->toEqual(999.0);
});

test('saveToPirep replaces the price with the computed value when auto pricing is enabled', function (): void {
    updateSetting('fares.auto_price', true);

    [, $saved] = saveFareToPirep();

    // (10 + 1000 * 0.11) * 1, replacing the configured 999
    expect((float) $saved->price)->toEqual(120.0);
});

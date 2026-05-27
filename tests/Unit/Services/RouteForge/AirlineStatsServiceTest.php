<?php

declare(strict_types=1);

use App\Models\Airline;
use App\Models\Airport;
use App\Models\Flight;
use App\Models\Subfleet;
use App\Models\User;
use App\Services\RouteForge\AirlineStatsService;

/*
 * Verifies the airline-wide stats snapshot consumed by /airline-stats and
 * embedded in the LintContext for L1's capacity hint.
 */

it('returns existing_active_flights_count = 0 for an airline with no flights', function (): void {
    $airline = Airline::factory()->create();

    $stats = (new AirlineStatsService())->buildFor($airline);

    expect($stats['existing_active_flights_count'])->toBe(0)
        ->and($stats['hub_airports'])->toBe([])
        ->and($stats['home_airport'])->toBeNull();
});

it('counts only enabled, non-owner flights toward existing_active_flights_count', function (): void {
    $airline = Airline::factory()->create();

    // Counted: enabled + null owner_type.
    Flight::factory()->count(3)->create([
        'airline_id' => $airline->id,
        'enabled'    => true,
        'owner_type' => null,
    ]);

    // Excluded: disabled.
    Flight::factory()->create([
        'airline_id' => $airline->id,
        'enabled'    => false,
        'owner_type' => null,
    ]);

    // Excluded: owner-typed (user-owned).
    Flight::factory()->create([
        'airline_id' => $airline->id,
        'enabled'    => true,
        'owner_type' => User::class,
        'owner_id'   => 1,
    ]);

    $stats = (new AirlineStatsService())->buildFor($airline);

    expect($stats['existing_active_flights_count'])->toBe(3);
});

it('returns distinct hub_id values from the airline subfleets', function (): void {
    $airline = Airline::factory()->create();
    $ksfo = Airport::factory()->create(['id' => 'KSFO', 'icao' => 'KSFO']);
    $klax = Airport::factory()->create(['id' => 'KLAX', 'icao' => 'KLAX']);

    Subfleet::factory()->create(['airline_id' => $airline->id, 'hub_id' => $ksfo->id]);
    Subfleet::factory()->create(['airline_id' => $airline->id, 'hub_id' => $ksfo->id]); // duplicate hub
    Subfleet::factory()->create(['airline_id' => $airline->id, 'hub_id' => $klax->id]);
    Subfleet::factory()->create(['airline_id' => $airline->id, 'hub_id' => null]);      // no hub set

    $stats = (new AirlineStatsService())->buildFor($airline);

    expect($stats['hub_airports'])->toHaveCount(2)
        ->and($stats['hub_airports'])->toContain('KSFO')
        ->and($stats['hub_airports'])->toContain('KLAX');
});

it('always returns null for home_airport (v1 has no airline-level home)', function (): void {
    $airline = Airline::factory()->create();
    $ksfo = Airport::factory()->create(['id' => 'KSFO', 'icao' => 'KSFO']);
    Subfleet::factory()->create(['airline_id' => $airline->id, 'hub_id' => $ksfo->id]);

    $stats = (new AirlineStatsService())->buildFor($airline);

    expect($stats['home_airport'])->toBeNull();
});

it('returns the documented array shape with the three expected keys', function (): void {
    $stats = (new AirlineStatsService())->buildFor(Airline::factory()->create());

    expect(array_keys($stats))->toBe([
        'existing_active_flights_count',
        'hub_airports',
        'home_airport',
    ]);
});

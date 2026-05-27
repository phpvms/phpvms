<?php

declare(strict_types=1);

use App\Enums\AircraftStatus;
use App\Enums\FlightType;
use App\Models\Aircraft;
use App\Models\Airline;
use App\Models\Subfleet;
use Database\Seeders\ShieldSeeder;

beforeEach(function (): void {
    $this->seed(ShieldSeeder::class);
    $this->actingAs(createAdminUser());
});

it('returns subfleets for the given airline with capability fields', function (): void {
    $airline = Airline::factory()->create();
    Subfleet::factory()->create([
        'airline_id'   => $airline->id,
        'cruise_speed' => 450,
        'max_range_nm' => 3500,
        'route_types'  => [FlightType::SCHED_PAX],
    ]);

    $response = $this->getJson('/admin/route-forge/api/subfleets?airline_id='.$airline->id)
        ->assertSuccessful();

    $data = $response->json('data');

    expect($data)->toHaveCount(1)
        ->and($data[0])->toMatchArray([
            'cruise_speed' => 450,
            'max_range_nm' => 3500,
            'route_types'  => ['J'],
        ])
        ->and($data[0]['aircraft_count'])->toBe(0);
});

it('serializes route_types as a single-char enum value list', function (): void {
    $airline = Airline::factory()->create();
    Subfleet::factory()->create([
        'airline_id'  => $airline->id,
        'route_types' => [FlightType::SCHED_PAX, FlightType::SCHED_CARGO, FlightType::CHARTER_PAX_ONLY],
    ]);

    $payload = $this->getJson('/admin/route-forge/api/subfleets?airline_id='.$airline->id)
        ->assertSuccessful()
        ->json('data');

    // Compare as multisets — order isn't part of the contract.
    expect($payload[0]['route_types'])->toEqualCanonicalizing(['J', 'F', 'C']);
});

it('serializes NULL route_types as null in the wire shape', function (): void {
    $airline = Airline::factory()->create();
    Subfleet::factory()->create([
        'airline_id'  => $airline->id,
        'route_types' => null,
    ]);

    $payload = $this->getJson('/admin/route-forge/api/subfleets?airline_id='.$airline->id)
        ->assertSuccessful()
        ->json('data');

    expect($payload[0]['route_types'])->toBeNull();
});

it('counts only active aircraft per subfleet', function (): void {
    $airline = Airline::factory()->create();
    $subfleet = Subfleet::factory()->create(['airline_id' => $airline->id]);

    Aircraft::factory()->count(2)->create([
        'subfleet_id' => $subfleet->id,
        'status'      => AircraftStatus::ACTIVE,
    ]);
    Aircraft::factory()->create([
        'subfleet_id' => $subfleet->id,
        'status'      => AircraftStatus::RETIRED,
    ]);

    $payload = $this->getJson('/admin/route-forge/api/subfleets?airline_id='.$airline->id)
        ->assertSuccessful()
        ->json('data');

    expect($payload[0]['aircraft_count'])->toBe(2);
});

it('rejects missing airline_id with a 422', function (): void {
    $this->getJson('/admin/route-forge/api/subfleets')
        ->assertStatus(422);
});

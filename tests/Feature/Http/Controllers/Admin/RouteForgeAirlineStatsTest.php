<?php

declare(strict_types=1);

use App\Models\Airline;
use App\Models\Flight;
use App\Models\User;
use Database\Seeders\ShieldSeeder;

beforeEach(function (): void {
    $this->seed(ShieldSeeder::class);
    $this->actingAs(createAdminUser());
});

it('returns the airline stats payload shape', function (): void {
    $airline = Airline::factory()->create();
    Flight::factory()->count(3)->create([
        'airline_id' => $airline->id,
        'enabled'    => true,
        'owner_type' => null,
    ]);

    $payload = $this->getJson('/admin/route-forge/api/airline-stats?airline_id='.$airline->id)
        ->assertSuccessful()
        ->json('data');

    expect($payload)->toHaveKeys(['existing_active_flights_count', 'hub_airports', 'home_airport'])
        ->and($payload['existing_active_flights_count'])->toBe(3)
        ->and($payload['hub_airports'])->toBeArray()
        ->and($payload['home_airport'])->toBeNull();
});

it('excludes owner-typed flights from the existing_active_flights_count', function (): void {
    $airline = Airline::factory()->create();
    Flight::factory()->create([
        'airline_id' => $airline->id,
        'enabled'    => true,
        'owner_type' => null,
    ]);
    Flight::factory()->create([
        'airline_id' => $airline->id,
        'enabled'    => true,
        'owner_type' => User::class,
        'owner_id'   => 1,
    ]);

    $payload = $this->getJson('/admin/route-forge/api/airline-stats?airline_id='.$airline->id)
        ->assertSuccessful()
        ->json('data');

    expect($payload['existing_active_flights_count'])->toBe(1);
});

it('rejects missing airline_id with a 422', function (): void {
    $this->getJson('/admin/route-forge/api/airline-stats')
        ->assertStatus(422);
});

it('returns 422 for an unknown airline (caught by exists validation)', function (): void {
    // Form Request rule `exists:airlines,id` fails before the controller's
    // findOrFail can fire, so this surfaces as a 422 validation error.
    $this->getJson('/admin/route-forge/api/airline-stats?airline_id=999999')
        ->assertStatus(422);
});

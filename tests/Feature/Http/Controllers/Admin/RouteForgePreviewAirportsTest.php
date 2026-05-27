<?php

declare(strict_types=1);

use App\Models\Airport;
use Database\Seeders\ShieldSeeder;

beforeEach(function (): void {
    $this->seed(ShieldSeeder::class);
    $this->actingAs(createAdminUser());
});

it('rejects unauthenticated requests', function (): void {
    auth()->logout();

    $this->getJson('/admin/route-forge/api/preview-airports')
        ->assertUnauthorized();
});

it('returns paginated airports with the RouteForgeAirportResource shape', function (): void {
    Airport::factory()->count(3)->create();

    $response = $this->getJson('/admin/route-forge/api/preview-airports?limit=10')
        ->assertSuccessful();

    $payload = $response->json();

    expect($payload)->toHaveKey('data')
        ->and($payload['data'])->toBeArray()
        ->and(count($payload['data']))->toBeGreaterThanOrEqual(3);

    // Verify the resource exposes the fields the TS client reads.
    $first = $payload['data'][0];
    foreach (['id', 'icao', 'iata', 'name', 'lat', 'lon', 'timezone', 'hub'] as $field) {
        expect($first)->toHaveKey($field);
    }
});

it('decorates results with distance_from_origin_nm when `near` is supplied', function (): void {
    // Origin at (37.6, -122.4) ≈ SFO; nearby airport at (37.7, -122.5).
    Airport::factory()->create([
        'id'   => 'KSFO',
        'icao' => 'KSFO',
        'lat'  => 37.6,
        'lon'  => -122.4,
    ]);
    Airport::factory()->create([
        'id'   => 'KOAK',
        'icao' => 'KOAK',
        'lat'  => 37.7,
        'lon'  => -122.5,
    ]);

    $response = $this->getJson('/admin/route-forge/api/preview-airports?near=KSFO&limit=50')
        ->assertSuccessful();

    $items = $response->json('data');
    $oakland = collect($items)->firstWhere('id', 'KOAK');

    expect($oakland)->not->toBeNull()
        ->and($oakland)->toHaveKey('distance_from_origin_nm')
        ->and($oakland['distance_from_origin_nm'])->toBeNumeric()
        ->and((float) $oakland['distance_from_origin_nm'])->toBeLessThan(20.0);
});

it('decorates results with in_subfleet_range when `max_range_nm` is supplied alongside `near`', function (): void {
    Airport::factory()->create([
        'id'  => 'KSFO',
        'lat' => 37.6,
        'lon' => -122.4,
    ]);
    Airport::factory()->create([
        'id'  => 'KOAK',
        'lat' => 37.7,
        'lon' => -122.5,
    ]);

    $response = $this->getJson('/admin/route-forge/api/preview-airports?near=KSFO&max_range_nm=500&limit=50')
        ->assertSuccessful();

    $oakland = collect($response->json('data'))->firstWhere('id', 'KOAK');

    expect($oakland)->toHaveKey('in_subfleet_range')
        ->and($oakland['in_subfleet_range'])->toBeTrue();
});

<?php

declare(strict_types=1);

use App\Models\Airline;
use App\Models\Flight;
use Database\Seeders\ShieldSeeder;
use Tests\Support\RouteForgeTestHelpers as RF;

beforeEach(function (): void {
    $this->seed(ShieldSeeder::class);
    $this->actingAs(createAdminUser());
});

it('returns matching duplicates keyed by submitted-row index', function (): void {
    $airline = Airline::factory()->create();
    $dpt = RF::airport('KSFO');
    $arr = RF::airport('KLAX');
    $existing = Flight::factory()->create([
        'airline_id'    => $airline->id,
        'flight_number' => 500,
        'route_code'    => '',
        'route_leg'     => '',
        'owner_type'    => null,
    ]);

    $payload = $this->postJson('/admin/route-forge/api/check-duplicates', [
        'airline_id' => $airline->id,
        'rows'       => [
            ['airline_id' => $airline->id, 'flight_number' => 499, 'dpt_airport_id' => $dpt->id, 'arr_airport_id' => $arr->id],
            ['airline_id' => $airline->id, 'flight_number' => 500, 'dpt_airport_id' => $dpt->id, 'arr_airport_id' => $arr->id],
        ],
    ])->assertSuccessful()->json('data');

    expect($payload)->toHaveKey('duplicates')
        ->and($payload['duplicates'])->toHaveCount(1)
        ->and($payload['duplicates'][0])->toMatchArray([
            'index'          => 1,
            'conflict_field' => 'flight_number',
        ])
        ->and((string) $payload['duplicates'][0]['existing_flight_id'])->toBe((string) $existing->id);
});

it('returns an empty duplicates array when no rows match', function (): void {
    $airline = Airline::factory()->create();
    $dpt = RF::airport('KJFK');
    $arr = RF::airport('KBOS');

    $payload = $this->postJson('/admin/route-forge/api/check-duplicates', [
        'airline_id' => $airline->id,
        'rows'       => [
            ['airline_id' => $airline->id, 'flight_number' => 999, 'dpt_airport_id' => $dpt->id, 'arr_airport_id' => $arr->id],
        ],
    ])->assertSuccessful()->json('data');

    expect($payload['duplicates'])->toBe([]);
});

it('rejects empty body with a 422', function (): void {
    $this->postJson('/admin/route-forge/api/check-duplicates', [])
        ->assertStatus(422);
});

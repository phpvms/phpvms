<?php

declare(strict_types=1);

use App\Models\Airline;
use App\Models\Flight;
use App\Models\FlightBundle;
use App\Models\Subfleet;
use Database\Seeders\ShieldSeeder;
use Tests\Support\RouteForgeTestHelpers as RF;

beforeEach(function (): void {
    $this->seed(ShieldSeeder::class);
    $this->actingAs(createAdminUser());
});

it('commits a happy-path batch and returns 201 with the wire envelope', function (): void {
    $airline = Airline::factory()->create();
    $subfleet = Subfleet::factory()->create(['airline_id' => $airline->id]);
    $dpt = RF::nextAirport();
    $arr = RF::nextAirport();

    $payload = RF::batchPayload($airline->id, $dpt->id, $arr->id, [
        'subfleet_ids' => [$subfleet->id],
        'rows'         => [
            ['airline_id' => $airline->id, 'flight_number' => 100, 'dpt_airport_id' => $dpt->id, 'arr_airport_id' => $arr->id],
            ['airline_id' => $airline->id, 'flight_number' => 101, 'dpt_airport_id' => $arr->id, 'arr_airport_id' => $dpt->id],
        ],
    ]);

    $response = $this->postJson('/admin/route-forge/api/commit', $payload)
        ->assertStatus(201);

    $body = $response->json('data');

    expect($body)->toHaveKeys(['bundle_id', 'batch_id', 'created_count', 'flight_ids', 'skipped'])
        ->and($body['created_count'])->toBe(2)
        ->and($body['flight_ids'])->toHaveCount(2)
        ->and($body['skipped'])->toBe([]);

    // Persistence side effects.
    $bundle = FlightBundle::query()->find($body['bundle_id']);
    expect($bundle)->not->toBeNull()
        ->and(Flight::query()->whereIn('id', $body['flight_ids'])->count())->toBe(2);
});

it('returns 422 with a LintReport body when rows violate an error rule (L6)', function (): void {
    $airline = Airline::factory()->create();
    $subfleet = Subfleet::factory()->create(['airline_id' => $airline->id]);
    $dpt = RF::nextAirport();
    $arr = RF::nextAirport();

    $payload = RF::batchPayload($airline->id, $dpt->id, $arr->id, [
        'subfleet_ids' => [$subfleet->id],
        'rows'         => [
            // Origin == destination → L6 error.
            ['airline_id' => $airline->id, 'flight_number' => 100, 'dpt_airport_id' => $dpt->id, 'arr_airport_id' => $dpt->id],
        ],
    ]);

    $bundleCountBefore = FlightBundle::query()->count();
    $flightCountBefore = Flight::query()->count();

    $response = $this->postJson('/admin/route-forge/api/commit', $payload)
        ->assertStatus(422);

    $body = $response->json('data');

    $l6 = collect($body['errors'])->firstWhere('rule', 'L6');

    expect($l6)->not->toBeNull()
        ->and($l6['severity'])->toBe('error')
        ->and(FlightBundle::query()->count())->toBe($bundleCountBefore)
        ->and(Flight::query()->count())->toBe($flightCountBefore);
});

it('returns 422 with an L4 error when rows include intra-batch duplicates', function (): void {
    $airline = Airline::factory()->create();
    $subfleet = Subfleet::factory()->create(['airline_id' => $airline->id]);
    $dpt = RF::nextAirport();
    $arr = RF::nextAirport();

    $payload = RF::batchPayload($airline->id, $dpt->id, $arr->id, [
        'subfleet_ids' => [$subfleet->id],
        'rows'         => [
            ['airline_id' => $airline->id, 'flight_number' => 100, 'dpt_airport_id' => $dpt->id, 'arr_airport_id' => $arr->id],
            ['airline_id' => $airline->id, 'flight_number' => 100, 'dpt_airport_id' => $dpt->id, 'arr_airport_id' => $arr->id],
        ],
    ]);

    $body = $this->postJson('/admin/route-forge/api/commit', $payload)
        ->assertStatus(422)
        ->json('data');

    $l4 = collect($body['errors'])->firstWhere('rule', 'L4');
    expect($l4)->not->toBeNull();
});

it('attaches to an existing bundle in attach-existing mode without persisting a new bundle', function (): void {
    $airline = Airline::factory()->create();
    $subfleet = Subfleet::factory()->create(['airline_id' => $airline->id]);
    $dpt = RF::nextAirport();
    $arr = RF::nextAirport();
    $existing = FlightBundle::factory()->create(['name' => 'Existing']);

    $payload = RF::batchPayload($airline->id, $dpt->id, $arr->id, [
        'subfleet_ids' => [$subfleet->id],
        'bundle'       => [
            'existing_bundle_id' => $existing->id,
            'name'               => null,
            'enabled'            => null,
        ],
        'rows' => [
            ['airline_id' => $airline->id, 'flight_number' => 200, 'dpt_airport_id' => $dpt->id, 'arr_airport_id' => $arr->id],
            ['airline_id' => $airline->id, 'flight_number' => 201, 'dpt_airport_id' => $arr->id, 'arr_airport_id' => $dpt->id],
        ],
    ]);

    $bundleCountBefore = FlightBundle::query()->count();

    $body = $this->postJson('/admin/route-forge/api/commit', $payload)
        ->assertStatus(201)
        ->json('data');

    expect($body['bundle_id'])->toBe($existing->id)
        ->and(FlightBundle::query()->count())->toBe($bundleCountBefore);
});

it('rejects bundle.existing_bundle_id that points at a soft-deleted bundle', function (): void {
    $airline = Airline::factory()->create();
    $subfleet = Subfleet::factory()->create(['airline_id' => $airline->id]);
    $dpt = RF::nextAirport();
    $arr = RF::nextAirport();
    $deleted = FlightBundle::factory()->create();
    $deleted->delete();

    $payload = RF::batchPayload($airline->id, $dpt->id, $arr->id, [
        'subfleet_ids' => [$subfleet->id],
        'bundle'       => [
            'existing_bundle_id' => $deleted->id,
            'name'               => null,
            'enabled'            => null,
        ],
    ]);

    $this->postJson('/admin/route-forge/api/commit', $payload)
        ->assertStatus(422);
});

it('rejects empty body with 422', function (): void {
    $this->postJson('/admin/route-forge/api/commit', [])
        ->assertStatus(422);
});

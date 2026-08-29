<?php

declare(strict_types=1);

use App\Enums\BundleType;
use App\Models\Airline;
use App\Models\Flight;
use App\Models\FlightBundle;
use App\Models\Subfleet;
use Database\Seeders\RolesPermissionsSeeder;
use Tests\Support\RouteForgeTestHelpers as RF;

beforeEach(function (): void {
    $this->seed(RolesPermissionsSeeder::class);
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

it('commits a tour batch whose destinations list is empty', function (): void {
    $airline = Airline::factory()->create();
    $subfleet = Subfleet::factory()->create(['airline_id' => $airline->id]);
    $a = RF::nextAirport();
    $b = RF::nextAirport();
    $c = RF::nextAirport();
    $bundle = FlightBundle::factory()->create(['type' => BundleType::Tour]);

    $payload = RF::batchPayload($airline->id, $a->id, $b->id, [
        'subfleet_ids' => [$subfleet->id],
        'origins'      => [$a->id, $b->id, $c->id],
        'bundle'       => [
            'existing_bundle_id' => $bundle->id,
            'name'               => null,
            'enabled'            => null,
        ],
        'rows' => [
            ['airline_id' => $airline->id, 'flight_number' => 9000, 'route_leg' => 1, 'dpt_airport_id' => $a->id, 'arr_airport_id' => $b->id],
            ['airline_id' => $airline->id, 'flight_number' => 9000, 'route_leg' => 2, 'dpt_airport_id' => $b->id, 'arr_airport_id' => $c->id],
        ],
    ]);
    // A tour's chain is origins alone. array_replace_recursive() cannot
    // override with an empty array, so the helper's default is cleared here.
    $payload['destinations'] = [];

    $body = $this->postJson('/admin/route-forge/api/commit', $payload)
        ->assertStatus(201)
        ->json('data');

    expect($body['created_count'])->toBe(2)
        ->and(
            Flight::query()->whereIn('id', $body['flight_ids'])
                ->orderBy('route_leg')->pluck('route_leg')->all()
        )->toBe([1, 2]);
});

it('commits rows carrying an explicit null flight_type, defaulting them to J', function (): void {
    $airline = Airline::factory()->create();
    $subfleet = Subfleet::factory()->create(['airline_id' => $airline->id]);
    $dpt = RF::nextAirport();
    $arr = RF::nextAirport();

    // The SPA always sends the flight_type key, null when the admin never
    // picked one; an explicit NULL in the bulk insert must not override the
    // column's NOT NULL default.
    $payload = RF::batchPayload($airline->id, $dpt->id, $arr->id, [
        'subfleet_ids' => [$subfleet->id],
        'rows'         => [
            ['airline_id' => $airline->id, 'flight_number' => 300, 'flight_type' => null, 'dpt_airport_id' => $dpt->id, 'arr_airport_id' => $arr->id],
        ],
    ]);

    $body = $this->postJson('/admin/route-forge/api/commit', $payload)
        ->assertStatus(201)
        ->json('data');

    expect(Flight::query()->find($body['flight_ids'][0])->flight_type->value)->toBe('J');
});

it('still rejects a batch whose destinations key is missing entirely', function (): void {
    $airline = Airline::factory()->create();
    $dpt = RF::nextAirport();
    $arr = RF::nextAirport();

    $payload = RF::batchPayload($airline->id, $dpt->id, $arr->id);
    unset($payload['destinations']);

    $this->postJson('/admin/route-forge/api/commit', $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['destinations']);
});

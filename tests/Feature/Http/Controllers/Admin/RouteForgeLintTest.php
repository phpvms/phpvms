<?php

declare(strict_types=1);

use App\Models\Airline;
use App\Models\Subfleet;
use Database\Seeders\RolesPermissionsSeeder;
use Tests\Support\RouteForgeTestHelpers as RF;

beforeEach(function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    $this->actingAs(createAdminUser());
});

it('runs the full lint catalog and returns a LintReport envelope', function (): void {
    $airline = Airline::factory()->create();
    $dpt = RF::nextAirport();
    $arr = RF::nextAirport();

    $payload = RF::batchPayload($airline->id, $dpt->id, $arr->id);

    $body = $this->postJson('/admin/route-forge/api/lint', $payload)
        ->assertSuccessful()
        ->json();

    expect($body)->toHaveKey('data')
        ->and($body['data'])->toHaveKeys(['errors', 'warnings', 'info']);
});

it('returns L4 error when two rows share the strict duplicate key', function (): void {
    $airline = Airline::factory()->create();
    $dpt = RF::nextAirport();
    $arr = RF::nextAirport();

    $payload = RF::batchPayload($airline->id, $dpt->id, $arr->id, [
        'rows' => [
            ['airline_id' => $airline->id, 'flight_number' => 100, 'dpt_airport_id' => $dpt->id, 'arr_airport_id' => $arr->id],
            ['airline_id' => $airline->id, 'flight_number' => 100, 'dpt_airport_id' => $dpt->id, 'arr_airport_id' => $arr->id],
        ],
    ]);

    $body = $this->postJson('/admin/route-forge/api/lint', $payload)
        ->assertSuccessful()
        ->json('data');

    $l4 = collect($body['errors'])->firstWhere('rule', 'L4');

    expect($l4)->not->toBeNull()
        ->and($l4['severity'])->toBe('error');
});

it('returns L9 warning when row count exceeds the soft cap', function (): void {
    $airline = Airline::factory()->create();
    $dpt = RF::nextAirport();
    $arr = RF::nextAirport();

    $rows = [];
    for ($i = 0; $i < 75; $i++) {
        $rows[] = [
            'airline_id'     => $airline->id,
            'flight_number'  => 100 + $i,
            'dpt_airport_id' => $dpt->id,
            'arr_airport_id' => $arr->id,
        ];
    }

    $payload = RF::batchPayload($airline->id, $dpt->id, $arr->id, ['rows' => $rows]);

    $body = $this->postJson('/admin/route-forge/api/lint', $payload)
        ->assertSuccessful()
        ->json('data');

    $l9 = collect($body['warnings'])->firstWhere('rule', 'L9');

    expect($l9)->not->toBeNull()
        ->and($l9['severity'])->toBe('warning')
        ->and(collect($body['errors'])->where('rule', 'L10'))->toHaveCount(0);
});

it('rejects malformed bundle envelope with 422', function (): void {
    $this->postJson('/admin/route-forge/api/lint', ['airline_id' => 'not-an-int'])
        ->assertStatus(422);
});

it('echoes selected subfleets into the L1 / L2 / L7 evaluation', function (): void {
    $airline = Airline::factory()->create();
    $subfleet = Subfleet::factory()->create([
        'airline_id'   => $airline->id,
        'max_range_nm' => 100, // forces L2 to fire for the long-distance row
    ]);
    $dpt = RF::nextAirport();
    $arr = RF::nextAirport();

    $payload = RF::batchPayload($airline->id, $dpt->id, $arr->id, [
        'subfleet_ids' => [$subfleet->id],
        'rows'         => [
            ['airline_id' => $airline->id, 'flight_number' => 200, 'dpt_airport_id' => $dpt->id, 'arr_airport_id' => $arr->id, 'distance_nm' => 3500],
        ],
    ]);

    $body = $this->postJson('/admin/route-forge/api/lint', $payload)
        ->assertSuccessful()
        ->json('data');

    expect(collect($body['warnings'])->where('rule', 'L2'))->toHaveCount(1);
});

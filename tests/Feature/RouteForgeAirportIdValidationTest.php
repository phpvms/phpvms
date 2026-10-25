<?php

declare(strict_types=1);

use App\Models\Airline;
use App\Models\Airport;
use Database\Seeders\RolesPermissionsSeeder;
use Tests\Support\RouteForgeTestHelpers as RF;

/**
 * The origin and destination rules used to carry `alpha`, which restricted an
 * airport id to letters. Airport ids are not letters-only -- the factory draws
 * from A-Z0-9, real ICAO identifiers carry digits, and the generated ids these
 * columns were widened for will carry digits, `-` and `_`. The `exists` rule
 * alongside is what actually decides whether an airport is usable, so `alpha`
 * could only reject one that `exists` had already accepted.
 *
 * Posted to the endpoint rather than handed to `Validator::make($rules)`,
 * because `BaseRouteForgeBatchRequest::prepareForValidation()` runs first. A
 * test that reads `rules()` on its own skips that step and can claim a payload
 * is accepted when a real client's would not be -- which is exactly how the
 * uppercasing that used to live in there went unnoticed here.
 */
beforeEach(function (): void {
    $this->seed(RolesPermissionsSeeder::class);
    $this->actingAs(createAdminUser());
});

function lintErrors(string $dpt, string $arr): array
{
    $airline = Airline::factory()->create();
    $payload = RF::batchPayload($airline->id, $dpt, $arr);

    $response = test()->postJson('/admin/route-forge/api/lint', $payload);

    if ($response->status() === 422) {
        return array_keys($response->json('errors') ?? []);
    }

    // Anything else has to be a success before an empty list can mean "no
    // validation errors" -- a 500 or a 403 would otherwise read as acceptance
    // and the two accepting tests below would pass on a broken endpoint.
    $response->assertSuccessful();

    return [];
}

it('accepts an existing airport id that carries digits', function (): void {
    Airport::factory()->create(['id' => 'K6L3', 'icao' => 'K6L3']);
    Airport::factory()->create(['id' => 'K7M4', 'icao' => 'K7M4']);

    expect(lintErrors('K6L3', 'K7M4'))->toBe([]);
});

/**
 * Mixed case on purpose: a nanoid carries both, and the request no longer
 * folds what it is given, so this is the shape a generated id actually has.
 */
it('accepts an existing airport id at the full column width', function (): void {
    Airport::factory()->create(['id' => 'V1a2B3c4D5e6F7g8', 'icao' => 'KJFK']);
    Airport::factory()->create(['id' => 'W1a2B3c4D5e6F7g8', 'icao' => 'KLAX']);

    expect(lintErrors('V1a2B3c4D5e6F7g8', 'W1a2B3c4D5e6F7g8'))->toBe([]);
});

it('still rejects an airport that does not exist', function (): void {
    expect(lintErrors('ZZZZ', 'ZZZY'))->not->toBe([]);
});

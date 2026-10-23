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
 * because `BaseRouteForgeBatchRequest::prepareForValidation()` runs first and
 * rewrites these values. A test that reads `rules()` on its own skips that
 * step and can claim a payload is accepted when a real client's would not be.
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

    return $response->status() === 422
        ? array_keys($response->json('errors') ?? [])
        : [];
}

it('accepts an existing airport id that carries digits', function (): void {
    Airport::factory()->create(['id' => 'K6L3', 'icao' => 'K6L3']);
    Airport::factory()->create(['id' => 'K7M4', 'icao' => 'K7M4']);

    expect(lintErrors('K6L3', 'K7M4'))->toBe([]);
});

it('accepts an existing airport id at the full column width', function (): void {
    Airport::factory()->create(['id' => 'V1A2B3C4D5E6F7G8', 'icao' => 'KJFK']);
    Airport::factory()->create(['id' => 'W1A2B3C4D5E6F7G8', 'icao' => 'KLAX']);

    expect(lintErrors('V1A2B3C4D5E6F7G8', 'W1A2B3C4D5E6F7G8'))->toBe([]);
});

it('still rejects an airport that does not exist', function (): void {
    expect(lintErrors('ZZZZ', 'ZZZY'))->not->toBe([]);
});

/**
 * Pinned because it is the gap this PR does not close, not because it is
 * wanted: `prepareForValidation()` still upper-cases these values, so a
 * generated id that carries lower case is folded into one that does not exist
 * and `exists` rejects it. Delete this test when that normaliser goes -- the
 * case above it will then cover the mixed-case id directly.
 */
it('rejects a mixed-case airport id because the request folds it', function (): void {
    Airport::factory()->create(['id' => 'V1a2B3c4D5e6F7g8', 'icao' => 'KJFK']);
    Airport::factory()->create(['id' => 'W1a2B3c4D5e6F7g8', 'icao' => 'KLAX']);

    expect(lintErrors('V1a2B3c4D5e6F7g8', 'W1a2B3c4D5e6F7g8'))->not->toBe([]);
});

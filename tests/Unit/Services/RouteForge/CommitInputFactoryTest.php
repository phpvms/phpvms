<?php

declare(strict_types=1);

use App\Models\Airline;
use App\Models\FlightBundle;
use App\Services\RouteForge\AirlineStatsService;
use App\Services\RouteForge\CommitInputFactory;
use App\Services\RouteForge\LintContextFactory;
use Illuminate\Validation\ValidationException;

/*
 * Verifies the factory that builds a CommitInput from validated payload.
 * Tests cover both bundle modes, the dropped TOCTOU defensive lookup
 * (single resolution + 422 on miss), causerId threading, and the
 * fare_multiplier coercion rules.
 */

function makeCommitInputFactory(): CommitInputFactory
{
    return new CommitInputFactory(new LintContextFactory(new AirlineStatsService()));
}

it('stamps created_by from causerId in create-new mode', function (): void {
    $airline = Airline::factory()->create();

    $input = makeCommitInputFactory()->fromValidatedPayload([
        'airline_id' => $airline->id,
        'bundle'     => [
            'name'    => 'New Batch',
            'enabled' => true,
        ],
        'rows' => [],
    ], causerId: 42);

    expect($input->existingBundle)->toBeNull()
        ->and($input->bundle->created_by)->toBe(42)
        ->and($input->causerId)->toBe(42);
});

it('does not stamp created_by in attach-existing mode and carries the resolved existing bundle', function (): void {
    $airline = Airline::factory()->create();
    $existing = FlightBundle::factory()->create([
        'name'       => 'Existing',
        'created_by' => null,
    ]);

    $input = makeCommitInputFactory()->fromValidatedPayload([
        'airline_id' => $airline->id,
        'bundle'     => [
            'existing_bundle_id' => $existing->id,
        ],
        'rows' => [],
    ], causerId: 42);

    expect($input->existingBundle)->toBeInstanceOf(FlightBundle::class)
        ->and($input->existingBundle->id)->toBe($existing->id)
        // The unsaved $input->bundle clone does NOT carry created_by from
        // the caller — only the existing bundle's persisted columns are
        // mirrored, and created_by is not in the mirror set.
        ->and($input->bundle->created_by)->toBeNull()
        ->and($input->causerId)->toBe(42);
});

it('raises ValidationException with bundle.existing_bundle_id key when the id is soft-deleted', function (): void {
    $airline = Airline::factory()->create();
    $existing = FlightBundle::factory()->create();
    $existing->delete();

    makeCommitInputFactory()->fromValidatedPayload([
        'airline_id' => $airline->id,
        'bundle'     => [
            'existing_bundle_id' => $existing->id,
        ],
        'rows' => [],
    ], causerId: 1);
})->throws(ValidationException::class);

it('writes the validation error against the bundle.existing_bundle_id field', function (): void {
    $airline = Airline::factory()->create();
    $existing = FlightBundle::factory()->create();
    $existing->delete();

    try {
        makeCommitInputFactory()->fromValidatedPayload([
            'airline_id' => $airline->id,
            'bundle'     => [
                'existing_bundle_id' => $existing->id,
            ],
            'rows' => [],
        ], causerId: 1);

        expect(false)->toBeTrue('Expected ValidationException');
    } catch (ValidationException $validationException) {
        expect($validationException->errors())->toHaveKey('bundle.existing_bundle_id');
    }
});

it('coerces empty fare_multiplier to null', function (): void {
    $airline = Airline::factory()->create();

    $input = makeCommitInputFactory()->fromValidatedPayload([
        'airline_id' => $airline->id,
        'bundle'     => [
            'name'            => 'X',
            'enabled'         => true,
            'fare_multiplier' => '',
        ],
        'rows' => [],
    ], causerId: 1);

    expect($input->fareMultiplier)->toBeNull();
});

it('passes a non-empty fare_multiplier through verbatim', function (): void {
    $airline = Airline::factory()->create();

    $input = makeCommitInputFactory()->fromValidatedPayload([
        'airline_id' => $airline->id,
        'bundle'     => [
            'name'            => 'X',
            'enabled'         => true,
            'fare_multiplier' => '+10%',
        ],
        'rows' => [],
    ], causerId: 1);

    expect($input->fareMultiplier)->toBe('+10%');
});

it('reuses the LintContextFactory bundle resolution — single flight_bundles lookup in attach-existing mode', function (): void {
    $airline = Airline::factory()->create();
    $existing = FlightBundle::factory()->create();

    DB::enableQueryLog();
    makeCommitInputFactory()->fromValidatedPayload([
        'airline_id' => $airline->id,
        'bundle'     => [
            'existing_bundle_id' => $existing->id,
        ],
        'rows' => [],
    ], causerId: 1);
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    $bundleQueries = collect($queries)->filter(
        static fn (array $q): bool => str_contains((string) $q['query'], '"flight_bundles"')
            || str_contains((string) $q['query'], '`flight_bundles`'),
    );

    // Exactly one: the CommitInputFactory::resolveExistingBundle lookup.
    // LintContextFactory::hydrateUnsavedBundle short-circuits because
    // $existingBundle is passed through.
    expect($bundleQueries)->toHaveCount(1);
});

it('coerces non-positive causerId to null on the unsaved bundle (matches prior controller behavior)', function (): void {
    $airline = Airline::factory()->create();

    $input = makeCommitInputFactory()->fromValidatedPayload([
        'airline_id' => $airline->id,
        'bundle'     => [
            'name'    => 'X',
            'enabled' => true,
        ],
        'rows' => [],
    ], causerId: 0);

    expect($input->bundle->created_by)->toBeNull()
        // causerId is passed through verbatim onto CommitInput for the
        // activity log; the controller previously stamped null when id is
        // missing but 0 is documented as the "no user" sentinel.
        ->and($input->causerId)->toBe(0);
});

it('builds subfleetIds as a list<int> from the validated payload', function (): void {
    $airline = Airline::factory()->create();

    $input = makeCommitInputFactory()->fromValidatedPayload([
        'airline_id'   => $airline->id,
        'subfleet_ids' => ['1', 2, '3'],
        'bundle'       => ['name' => 'X', 'enabled' => true],
        'rows'         => [],
    ], causerId: 1);

    expect($input->subfleetIds)->toBe([1, 2, 3]);
});

<?php

declare(strict_types=1);

use App\Enums\FlightType;
use App\Models\Airline;
use App\Models\Event;
use App\Models\FlightBundle;
use App\Models\Subfleet;
use App\Services\RouteForge\AirlineStatsService;
use App\Services\RouteForge\LintContextFactory;

/*
 * Verifies the factory that lifts buildLintContext / hydrateUnsavedBundle
 * out of RouteForgeController. Tests cover create-new mode, attach-existing
 * mode (both with caller-supplied and id-only payloads), event resolution,
 * flight_type pass-through, and the airline stats integration.
 */

function makeLintContextFactory(): LintContextFactory
{
    return new LintContextFactory(new AirlineStatsService());
}

it('builds an unsaved FlightBundle from the request payload in create-new mode', function (): void {
    $airline = Airline::factory()->create();

    $ctx = makeLintContextFactory()->fromValidatedPayload([
        'airline_id' => $airline->id,
        'bundle'     => [
            'name'        => 'Summer 2026',
            'description' => 'season opener',
            'enabled'     => true,
            'start_date'  => '2026-06-01',
            'end_date'    => '2026-08-31',
        ],
        'rows' => [],
    ]);

    expect($ctx->bundle)->toBeInstanceOf(FlightBundle::class)
        ->and($ctx->bundle->exists)->toBeFalse()
        ->and($ctx->bundle->name)->toBe('Summer 2026')
        ->and($ctx->bundle->description)->toBe('season opener')
        ->and($ctx->bundle->enabled)->toBeTrue();
});

it('mirrors start_date and end_date from a caller-supplied existing bundle without re-querying', function (): void {
    $airline = Airline::factory()->create();
    $existing = FlightBundle::factory()->create([
        'name'       => 'Winter Ops',
        'enabled'    => true,
        'start_date' => '2026-12-01',
        'end_date'   => '2027-02-28',
    ]);

    $ctx = makeLintContextFactory()->fromValidatedPayload([
        'airline_id' => $airline->id,
        'bundle'     => [
            // Even if the request body carries different name/dates, the
            // mirror copies from $existingBundle (Decision 3 wire).
            'name'       => 'IGNORED',
            'enabled'    => false,
            'start_date' => '2099-01-01',
            'end_date'   => '2099-12-31',
        ],
        'rows' => [],
    ], existingBundle: $existing);

    expect($ctx->bundle->exists)->toBeFalse()
        ->and($ctx->bundle->name)->toBe('Winter Ops')
        ->and($ctx->bundle->enabled)->toBeTrue()
        ->and((string) $ctx->bundle->start_date->format('Y-m-d'))->toBe('2026-12-01')
        ->and((string) $ctx->bundle->end_date->format('Y-m-d'))->toBe('2027-02-28');
});

it('resolves existing_bundle_id from the payload when no existingBundle parameter is supplied', function (): void {
    $airline = Airline::factory()->create();
    $existing = FlightBundle::factory()->create([
        'name'       => 'Lookup Path',
        'start_date' => '2026-03-01',
        'end_date'   => '2026-03-31',
    ]);

    $ctx = makeLintContextFactory()->fromValidatedPayload([
        'airline_id' => $airline->id,
        'bundle'     => [
            'existing_bundle_id' => $existing->id,
            'name'               => 'NOT USED',
            'enabled'            => true,
        ],
        'rows' => [],
    ]);

    expect($ctx->bundle->name)->toBe('Lookup Path')
        ->and((string) $ctx->bundle->start_date->format('Y-m-d'))->toBe('2026-03-01');
});

it('falls back to request-body bundle data when existing_bundle_id misses (soft-deleted)', function (): void {
    $airline = Airline::factory()->create();
    $existing = FlightBundle::factory()->create();
    $existing->delete(); // soft delete after validation

    $ctx = makeLintContextFactory()->fromValidatedPayload([
        'airline_id' => $airline->id,
        'bundle'     => [
            'existing_bundle_id' => $existing->id,
            'name'               => 'Fallback Body',
            'enabled'            => false,
        ],
        'rows' => [],
    ]);

    // Non-destructive lint: stale id falls through to body fields.
    expect($ctx->bundle->name)->toBe('Fallback Body')
        ->and($ctx->bundle->enabled)->toBeFalse();
});

it('resolves event_id to an Event model or null', function (): void {
    $airline = Airline::factory()->create();
    $event = Event::factory()->create();

    $withEvent = makeLintContextFactory()->fromValidatedPayload([
        'airline_id' => $airline->id,
        'event_id'   => $event->id,
        'bundle'     => ['name' => 'X', 'enabled' => true],
        'rows'       => [],
    ]);

    $withoutEvent = makeLintContextFactory()->fromValidatedPayload([
        'airline_id' => $airline->id,
        'bundle'     => ['name' => 'X', 'enabled' => true],
        'rows'       => [],
    ]);

    expect($withEvent->event)->toBeInstanceOf(Event::class)
        ->and($withEvent->event->id)->toBe($event->id)
        ->and($withoutEvent->event)->toBeNull();
});

it('passes flight_type through as a FlightType enum case', function (): void {
    $airline = Airline::factory()->create();

    $ctx = makeLintContextFactory()->fromValidatedPayload([
        'airline_id'  => $airline->id,
        'flight_type' => FlightType::SCHED_PAX->value,
        'bundle'      => ['name' => 'X', 'enabled' => true],
        'rows'        => [],
    ]);

    expect($ctx->flightType)->toBe(FlightType::SCHED_PAX);
});

it('eager-loads selected subfleets with aircraft and fares relations', function (): void {
    $airline = Airline::factory()->create();
    $sf = Subfleet::factory()->create(['airline_id' => $airline->id]);

    $ctx = makeLintContextFactory()->fromValidatedPayload([
        'airline_id'   => $airline->id,
        'subfleet_ids' => [$sf->id],
        'bundle'       => ['name' => 'X', 'enabled' => true],
        'rows'         => [],
    ]);

    expect($ctx->selectedSubfleets)->toHaveCount(1)
        ->and($ctx->selectedSubfleets->first()->relationLoaded('aircraft'))->toBeTrue()
        ->and($ctx->selectedSubfleets->first()->relationLoaded('fares'))->toBeTrue();
});

it('populates airline stats from AirlineStatsService', function (): void {
    $airline = Airline::factory()->create();

    $ctx = makeLintContextFactory()->fromValidatedPayload([
        'airline_id' => $airline->id,
        'bundle'     => ['name' => 'X', 'enabled' => true],
        'rows'       => [],
    ]);

    expect($ctx->airlineStats)->toHaveKey('existing_active_flights_count')
        ->and($ctx->airlineStats)->toHaveKey('hub_airports')
        ->and($ctx->airlineStats)->toHaveKey('home_airport')
        ->and($ctx->airlineStats['existing_active_flights_count'])->toBe(0);
});

it('does not re-query flight_bundles when an existingBundle parameter is passed', function (): void {
    $airline = Airline::factory()->create();
    $existing = FlightBundle::factory()->create();

    DB::enableQueryLog();
    makeLintContextFactory()->fromValidatedPayload([
        'airline_id' => $airline->id,
        'bundle'     => [
            'existing_bundle_id' => $existing->id, // would normally trigger a find
        ],
        'rows' => [],
    ], existingBundle: $existing);
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    $bundleQueries = collect($queries)->filter(
        static fn (array $q): bool => str_contains((string) $q['query'], '"flight_bundles"')
            || str_contains((string) $q['query'], '`flight_bundles`'),
    );

    expect($bundleQueries)->toBeEmpty();
});

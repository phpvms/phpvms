<?php

declare(strict_types=1);

use App\Models\Fare;
use App\Models\Flight;
use App\Models\Subfleet;
use App\Services\FareService;

/**
 * Direct contract tests for FareService::getFareWithOverrides.
 *
 * FinanceTest covers this indirectly through getReconciledFaresForFlight and
 * getAllFares, but nothing pinned the override-matching rule itself. That rule
 * is "a flight fare overrides the subfleet fare with the SAME fare id", and it
 * is the specific behaviour that the keyed-lookup optimisation replaced a
 * linear scan with — so it deserves a test that fails if the matching changes.
 */
beforeEach(function (): void {
    $this->fareSvc = app(FareService::class);
});

test('a flight fare overrides the subfleet fare with the same id', function (): void {
    $subfleet = Subfleet::factory()->create();
    $flight = Flight::factory()->create();

    $fareA = Fare::factory()->create(['price' => 100, 'cost' => 50, 'capacity' => 10]);
    $fareB = Fare::factory()->create(['price' => 200, 'cost' => 80, 'capacity' => 20]);

    $subfleet->fares()->attach($fareA->id, ['price' => 111, 'cost' => 55, 'capacity' => 11]);
    $subfleet->fares()->attach($fareB->id, ['price' => 222, 'cost' => 88, 'capacity' => 22]);

    // The flight overrides only fare B.
    $flight->fares()->attach($fareB->id, ['price' => 999, 'cost' => 111, 'capacity' => 33]);

    $subfleet->load('fares');
    $flight->load('fares');

    $out = $this->fareSvc->getFareWithOverrides($subfleet->fares, $flight->fares)->keyBy('id');

    expect($out)->toHaveCount(2);

    // A keeps the subfleet's pivot values.
    expect((int) $out[$fareA->id]->price)->toBe(111)
        ->and((int) $out[$fareA->id]->capacity)->toBe(11);

    // B takes the flight's.
    expect((int) $out[$fareB->id]->price)->toBe(999)
        ->and((int) $out[$fareB->id]->capacity)->toBe(33);
});

test('the result is driven by the subfleet fares, so a flight only fare is ignored', function (): void {
    $subfleet = Subfleet::factory()->create();
    $flight = Flight::factory()->create();

    $onSubfleet = Fare::factory()->create(['price' => 100]);
    $onFlightOnly = Fare::factory()->create(['price' => 500]);

    $subfleet->fares()->attach($onSubfleet->id, ['price' => 111]);
    $flight->fares()->attach($onFlightOnly->id, ['price' => 999]);

    $subfleet->load('fares');
    $flight->load('fares');

    $out = $this->fareSvc->getFareWithOverrides($subfleet->fares, $flight->fares);

    expect($out)->toHaveCount(1)
        ->and($out->first()->id)->toBe($onSubfleet->id);
});

test('with no subfleet fares the flight fares are returned directly', function (): void {
    $flight = Flight::factory()->create();
    $fare = Fare::factory()->create(['price' => 100]);
    $flight->fares()->attach($fare->id, ['price' => 777]);
    $flight->load('fares');

    $out = $this->fareSvc->getFareWithOverrides(collect(), $flight->fares);

    expect($out)->toHaveCount(1)
        ->and((int) $out->first()->price)->toBe(777);
});

test('empty on both sides returns an empty collection', function (): void {
    expect($this->fareSvc->getFareWithOverrides(collect(), collect()))->toBeEmpty()
        ->and($this->fareSvc->getFareWithOverrides(null, null))->toBeEmpty();
});

test('a subfleet fare with no matching flight fare is untouched', function (): void {
    $subfleet = Subfleet::factory()->create();
    $flight = Flight::factory()->create();

    $fare = Fare::factory()->create(['price' => 100, 'capacity' => 10]);
    $other = Fare::factory()->create(['price' => 300]);

    $subfleet->fares()->attach($fare->id, ['price' => 111, 'capacity' => 11]);
    // Flight overrides a completely different fare — must not bleed across.
    $flight->fares()->attach($other->id, ['price' => 999]);

    $subfleet->load('fares');
    $flight->load('fares');

    $out = $this->fareSvc->getFareWithOverrides($subfleet->fares, $flight->fares);

    expect($out)->toHaveCount(1)
        ->and((int) $out->first()->price)->toBe(111)
        ->and((int) $out->first()->capacity)->toBe(11);
});

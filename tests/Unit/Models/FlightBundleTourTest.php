<?php

declare(strict_types=1);

use App\Enums\BundleType;
use App\Models\Flight;
use App\Models\FlightBundle;

/**
 * Builds a bundle whose flights carry exactly the given `route_leg` values, in
 * the order given. FlightFactory otherwise randomizes route_leg.
 *
 * @param list<int|null> $legs
 */
function bundleWithLegs(array $legs): FlightBundle
{
    $bundle = FlightBundle::factory()->create();

    foreach ($legs as $leg) {
        Flight::factory()->create([
            'bundle_id' => $bundle->id,
            'route_leg' => $leg,
        ]);
    }

    return $bundle;
}

it('defaults a bundle to the flights type, in memory and in the column', function (): void {
    $bundle = FlightBundle::factory()->create();

    expect($bundle->type)->toBe(BundleType::Flights)
        ->and($bundle->fresh()->type)->toBe(BundleType::Flights);
});

it('casts the type column to the enum', function (): void {
    $bundle = FlightBundle::factory()->create(['type' => BundleType::Tour]);

    expect($bundle->fresh()->type)->toBe(BundleType::Tour);
});

it('logs a type change, since type is fillable', function (): void {
    $bundle = FlightBundle::factory()->create();

    $bundle->update(['type' => BundleType::Tour]);

    expect($bundle->activities()->latest('id')->first()?->changes()['attributes'] ?? [])
        ->toHaveKey('type');
});

it('accepts a contiguous sequence from 1', function (): void {
    $sequence = bundleWithLegs([1, 2, 3])->tourLegSequence();

    expect($sequence['valid'])->toBeTrue()
        ->and($sequence['problem'])->toBeNull()
        ->and($sequence['leg'])->toBeNull()
        ->and($sequence['flights'])->toHaveCount(3)
        ->and($sequence['flights']->pluck('route_leg')->all())->toBe([1, 2, 3]);
});

it('names the first missing leg in a gapped sequence', function (): void {
    $sequence = bundleWithLegs([1, 2, 4])->tourLegSequence();

    expect($sequence['valid'])->toBeFalse()
        ->and($sequence['problem'])->toBe('missing')
        ->and($sequence['leg'])->toBe(3);
});

it('names a duplicated leg', function (): void {
    $sequence = bundleWithLegs([1, 2, 2])->tourLegSequence();

    expect($sequence['valid'])->toBeFalse()
        ->and($sequence['problem'])->toBe('duplicate')
        ->and($sequence['leg'])->toBe(2);
});

it('reports an unnumbered flight as the leg it failed to fill', function (): void {
    $sequence = bundleWithLegs([1, null])->tourLegSequence();

    expect($sequence['valid'])->toBeFalse()
        ->and($sequence['problem'])->toBe('missing')
        ->and($sequence['leg'])->toBe(2);
});

it('treats route_leg 0 as unnumbered, not as a leg', function (): void {
    // Flight::routeLeg() canonicalizes 0 to NULL, so leg 1 is the one missing —
    // not leg 0, which cannot exist.
    $sequence = bundleWithLegs([0])->tourLegSequence();

    expect($sequence['valid'])->toBeFalse()
        ->and($sequence['problem'])->toBe('missing')
        ->and($sequence['leg'])->toBe(1);
});

it('rejects an empty bundle rather than calling it contiguous', function (): void {
    $sequence = FlightBundle::factory()->create()->tourLegSequence();

    expect($sequence['valid'])->toBeFalse()
        ->and($sequence['problem'])->toBe('empty')
        ->and($sequence['leg'])->toBeNull()
        ->and($sequence['flights'])->toBeEmpty();
});

it('rejects a sequence that does not start at 1', function (): void {
    $sequence = bundleWithLegs([2, 3])->tourLegSequence();

    expect($sequence['valid'])->toBeFalse()
        ->and($sequence['problem'])->toBe('missing')
        ->and($sequence['leg'])->toBe(1);
});

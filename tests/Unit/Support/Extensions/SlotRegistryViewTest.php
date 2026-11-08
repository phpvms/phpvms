<?php

declare(strict_types=1);

use App\Support\Extensions\SlotRegistry;

it('accepts an entry with only a Blade view', function (): void {
    $registry = new SlotRegistry()->register(['slot' => 'bids.row.actions', 'view' => 'acme::fly']);

    expect($registry->bladeEntriesFor('bids.row.actions'))->toHaveCount(1);
});

it('accepts an entry with both a component and a view', function (): void {
    $registry = new SlotRegistry()->register(['slot' => 'bids.row.actions', 'component' => 'AcmeFly', 'view' => 'acme::fly']);

    expect($registry->all()[0])->toMatchArray(['component' => 'AcmeFly', 'view' => 'acme::fly', 'order' => 100]);
});

it('rejects an entry with neither a component nor a view, naming the slot', function (): void {
    expect(fn (): SlotRegistry => new SlotRegistry()->register(['slot' => 'bids.row.actions']))
        ->toThrow(InvalidArgumentException::class, 'bids.row.actions');
});

it('returns only Blade entries for the slot, in ascending order', function (): void {
    $registry = new SlotRegistry()
        ->register(['slot' => 'bids.row.actions', 'view' => 'late', 'order' => 200])
        ->register(['slot' => 'bids.row.actions', 'component' => 'VueOnly'])
        ->register(['slot' => 'other.slot', 'view' => 'elsewhere'])
        ->register(['slot' => 'bids.row.actions', 'view' => 'early', 'order' => 100]);

    expect(array_column($registry->bladeEntriesFor('bids.row.actions'), 'view'))->toBe(['early', 'late']);
});

it('keeps Blade-only entries out of the SPA export', function (): void {
    $registry = new SlotRegistry()
        ->register(['slot' => 'bids.row.actions', 'view' => 'acme::fly'])
        ->register(['slot' => 'bids.row.actions', 'component' => 'AcmeFly', 'view' => 'acme::fly']);

    expect(array_column($registry->all(), 'component'))->toBe(['AcmeFly'])
        ->and($registry->bladeEntriesFor('bids.row.actions'))->toHaveCount(2);
});

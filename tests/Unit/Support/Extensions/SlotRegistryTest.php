<?php

declare(strict_types=1);

use App\Features\Hooks\Fill;
use App\Support\Extensions\Facades\Extensions;
use App\Support\Extensions\SlotRegistry;

it('keeps only vue fills, as SPA entries ordered by list position', function (): void {
    $registry = new SlotRegistry();

    $registry->addAll('bids.row.actions', [
        Fill::vue('First', null, ['bid' => '@bid']),
        Fill::html('<b>blade only</b>'),
        Fill::view('acme::fly'),
        Fill::vue('Fourth', '/ext/fourth.js'),
    ]);

    expect($registry->all())->toBe([
        ['slot' => 'bids.row.actions', 'component' => 'First', 'module' => null, 'view' => null, 'props' => ['bid' => '@bid'], 'order' => 0],
        ['slot' => 'bids.row.actions', 'component' => 'Fourth', 'module' => '/ext/fourth.js', 'view' => null, 'props' => [], 'order' => 30],
    ]);
});

it('starts empty again after the scoped instances are forgotten, as Octane does between requests', function (): void {
    Extensions::slots()->add('profile.cards', Fill::vue('Foo'), 0);

    expect(Extensions::toArray()['slots'])->toHaveCount(1);

    app()->forgetScopedInstances();

    expect(Extensions::toArray()['slots'])->toBe([]);
});

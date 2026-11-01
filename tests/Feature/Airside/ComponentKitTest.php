<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;

require_once __DIR__.'/Support.php';

/**
 * The x-vms:: anonymous components the pages are built from.
 */
beforeEach(function (): void {
    useAirside();
    $this->withoutVite();
});

it('gives unnamed drawers unique ids', function (): void {
    // Two unnamed drawers must not share a slide id, or one trigger opens both.
    $pair = Blade::render('<x-vms::ui.drawer title="A" /><x-vms::ui.drawer title="B" />');
    preg_match_all('/x-on:slide:(drawer-[A-Za-z0-9]+)-open\.window/', $pair, $ids);
    expect($ids[1])->toHaveCount(2)->and($ids[1][0])->not->toBe($ids[1][1]);
});

<?php

declare(strict_types=1);

use App\Support\Skylight\Facades\Skylight;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;

/**
 * theme-slot-outlets: Blade themes render slot outlets.
 */
beforeEach(function (): void {
    View::addNamespace('slottest', base_path('tests/fixtures/views/slots'));
});

function renderOutlet(): string
{
    return trim(Blade::render('<x-slot-outlet name="bids.row.actions" :context="[\'bid\' => 7]" />'));
}

it('renders a Blade entry with the context and resolved @ props', function (): void {
    Skylight::slots()->register(['slot' => 'bids.row.actions', 'view' => 'slottest::fly', 'props' => ['target' => '@bid']]);

    expect(renderOutlet())->toBe('Fly 7 via 7');
});

it('renders entries in ascending order', function (): void {
    Skylight::slots()
        ->register(['slot' => 'bids.row.actions', 'view' => 'slottest::second', 'order' => 200])
        ->register(['slot' => 'bids.row.actions', 'view' => 'slottest::fly', 'order' => 100, 'props' => ['target' => 'x']]);

    expect(preg_split('/\s+/', renderOutlet()))->toBe(['Fly', '7', 'via', 'x', 'second']);
});

it('skips a Vue-only entry and renders nothing', function (): void {
    Skylight::slots()->register(['slot' => 'bids.row.actions', 'component' => 'VueOnly']);

    expect(renderOutlet())->toBe('');
});

it('keeps rendering the other entries when one view throws, and logs it', function (): void {
    config(['app.debug' => false]);
    Log::spy();
    Skylight::slots()
        ->register(['slot' => 'bids.row.actions', 'view' => 'slottest::boom'])
        ->register(['slot' => 'bids.row.actions', 'view' => 'slottest::second', 'order' => 200]);

    expect(renderOutlet())->toBe('second');
    Log::shouldHaveReceived('warning')->once();
});

<?php

declare(strict_types=1);

use App\Features\Hooks\Facades\Hooks;
use App\Features\Hooks\Fill;
use App\Features\Hooks\Points\RenderingPirepDetail;
use App\Models\Pirep;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;

/**
 * Blade themes render the fills of a render hook point.
 */
final class OutletFillsInOrder
{
    public function handle(RenderingPirepDetail $hook): void
    {
        $hook->add(Fill::view('slottest::second'), Fill::html('<b>html</b>'), Fill::view('slottest::fly', ['bid' => 7, 'target' => 'x']));
    }
}

final class OutletVueOnly
{
    public function handle(RenderingPirepDetail $hook): void
    {
        $hook->add(Fill::vue('VueOnly'));
    }
}

final class OutletOneThrows
{
    public function handle(RenderingPirepDetail $hook): void
    {
        $hook->add(Fill::view('slottest::boom'), Fill::view('slottest::second'));
    }
}

beforeEach(function (): void {
    View::addNamespace('slottest', base_path('tests/fixtures/views/slots'));
});

function renderHookOutlet(): string
{
    return trim(Blade::render('<x-render-hook :hook="$hook" />', ['hook' => new RenderingPirepDetail(new Pirep())]));
}

it('renders view and html fills in list order', function (): void {
    Hooks::listen(RenderingPirepDetail::class, OutletFillsInOrder::class);

    expect(preg_split('/\s+/', renderHookOutlet()))->toBe(['second', '<b>html</b>', 'Fly', '7', 'via', 'x']);
});

it('renders nothing for a vue-only list', function (): void {
    Hooks::listen(RenderingPirepDetail::class, OutletVueOnly::class);

    expect(renderHookOutlet())->toBe('');
});

it('keeps rendering the other fills when one view throws, and logs it', function (): void {
    config(['app.debug' => false]);
    Log::spy();
    Hooks::listen(RenderingPirepDetail::class, OutletOneThrows::class);

    expect(renderHookOutlet())->toBe('second');
    Log::shouldHaveReceived('warning')->once();
});

it('renders nothing when no listener added a fill', function (): void {
    expect(renderHookOutlet())->toBe('');
});

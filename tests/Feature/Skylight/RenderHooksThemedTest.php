<?php

declare(strict_types=1);

use App\Features\Hooks\Facades\Hooks;
use App\Features\Hooks\Fill;
use App\Features\Hooks\Points\RenderingBidDrawerActions;
use App\Features\Hooks\Points\RenderingBidRowActions;
use App\Support\Extensions\Facades\Extensions;
use Igaster\LaravelTheme\Facades\Theme;

final class ThemedRowActionsListener
{
    public function handle(RenderingBidRowActions $hook): void
    {
        $hook->add(Fill::vue('Foo'), Fill::html('<i>blade</i>'), Fill::vue('Bar', '/ext/bar.js'));
    }
}

function renderHooksForTheme(): array
{
    return [RenderingBidRowActions::forList(), RenderingBidDrawerActions::forList()];
}

it('dispatches no render hook on the Blade path', function (): void {
    Theme::set('seven');
    $fake = Hooks::fake();
    $built = 0;

    response()->themed('Foo', 'flights.bids', bladeData: [], renderHooks: function () use (&$built): array {
        $built++;

        return renderHooksForTheme();
    });

    $fake->assertNotDispatched(RenderingBidRowActions::class);
    $fake->assertNotDispatched(RenderingBidDrawerActions::class);

    expect($built)->toBe(0);
});

it('dispatches each render hook once on the SPA path, for the direct and the presenter path', function (): void {
    Theme::set('skylight');
    $fake = Hooks::fake();

    response()->themed('Foo', 'flights.bids', spa: [], renderHooks: renderHooksForTheme());

    $presenter = new class()
    {
        public function toInertiaArray(): array
        {
            return [];
        }
    };
    response()->themed('Foo', 'flights.bids', $presenter, renderHooks: [RenderingBidRowActions::forList()]);

    $fake->assertDispatched(RenderingBidDrawerActions::class);
    // Row hook: once on the direct path, once on the presenter path.
    $rows = 0;
    $fake->assertDispatched(RenderingBidRowActions::class, function () use (&$rows): bool {
        $rows++;

        return true;
    });
    expect($rows)->toBe(2);
});

it("stores the vue fills of a hook's final list in the slots, in list order", function (): void {
    Theme::set('skylight');
    Hooks::listen(RenderingBidRowActions::class, ThemedRowActionsListener::class);

    response()->themed('Foo', 'flights.bids', spa: [], renderHooks: renderHooksForTheme());

    expect(Extensions::toArray()['slots'])->toBe([
        ['slot' => 'bids.row.actions', 'component' => 'Foo', 'module' => null, 'view' => null, 'props' => [], 'order' => 0],
        ['slot' => 'bids.row.actions', 'component' => 'Bar', 'module' => '/ext/bar.js', 'view' => null, 'props' => [], 'order' => 20],
    ]);
});

<?php

declare(strict_types=1);

require_once __DIR__.'/../Support.php';

use App\Features\Dashboard\Livewire\ActivityFeed;
use App\Features\Dashboard\Livewire\DashboardBoard;
use App\Features\Dashboard\Support\RouteReadout;
use App\Features\Dashboard\WidgetCatalog;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * 7.5: the dashboard board (layout from the browser, add, remove, reset, move),
 * the route readout and the activity feed's relative times.
 */
beforeEach(function (): void {
    $this->pilot = useAirside();
    $this->actingAs($this->pilot);
    $this->withoutVite();
});

function dashboardBoard(): Testable
{
    return Livewire::test(DashboardBoard::class, ['route' => ['from' => null, 'to' => null]]);
}

it('places the default widgets when the browser has no layout', function (): void {
    dashboardBoard()
        ->call('loadLayout', null)
        ->assertSet('ready', true)
        ->assertSet('grid', fn (array $ids): bool => $ids === ['route', 'activity'])
        ->assertSet('sidebar', []);
});

it('renders only the widgets in the layout from the browser and drops unknown ids', function (): void {
    dashboardBoard()
        ->call('loadLayout', ['grid' => ['activity', 'addon-weather', 'activity'], 'sidebar' => ['route', 5]])
        ->assertSet('grid', ['activity'])
        ->assertSet('sidebar', ['route']);
});

it('keeps an empty saved layout empty', function (): void {
    dashboardBoard()
        ->call('loadLayout', ['grid' => [], 'sidebar' => []])
        ->assertSet('grid', [])
        ->assertSet('sidebar', []);
});

it('falls back to the default for a broken layout', function (): void {
    dashboardBoard()
        ->call('loadLayout', ['grid' => 'nope'])
        ->assertSet('grid', fn (array $ids): bool => $ids === ['route', 'activity']);
});

it('toggles editing mode', function (): void {
    dashboardBoard()
        ->call('loadLayout', null)
        ->call('toggleEditing')
        ->assertSet('editing', true)
        ->call('toggleEditing')
        ->assertSet('editing', false);
});

it('removes a widget and adds it back', function (): void {
    dashboardBoard()
        ->call('loadLayout', null)
        ->call('toggleEditing')
        ->call('removeWidget', 'activity')
        ->assertSet('grid', ['route'])
        ->assertDispatched('dashboard-layout-changed', grid: ['route'], sidebar: [])
        ->call('addWidget', 'activity')
        ->assertSet('grid', fn (array $ids): bool => $ids === ['route', 'activity']);
});

it('adds a widget once and ignores an unknown id', function (): void {
    dashboardBoard()
        ->call('loadLayout', null)
        ->call('addWidget', 'route')
        ->call('addWidget', 'addon-weather')
        ->assertSet('grid', fn (array $ids): bool => $ids === ['route', 'activity'])
        ->assertNotDispatched('dashboard-layout-changed');
});

it('resets to the default layout', function (): void {
    dashboardBoard()
        ->call('loadLayout', ['grid' => [], 'sidebar' => ['activity']])
        ->call('resetLayout')
        ->assertSet('grid', fn (array $ids): bool => $ids === ['route', 'activity'])
        ->assertSet('sidebar', [])
        ->assertDispatched('dashboard-layout-changed', grid: ['route', 'activity'], sidebar: []);
});

it('moves a widget within a zone and between zones', function (): void {
    dashboardBoard()
        ->call('loadLayout', null)
        ->call('moveWidget', 'activity', 'grid', 0)
        ->assertSet('grid', fn (array $ids): bool => $ids === ['activity', 'route'])
        ->call('moveWidget', 'route', 'sidebar', 0)
        ->assertSet('grid', ['activity'])
        ->assertSet('sidebar', ['route'])
        ->call('moveWidget', 'activity', 'sidebar', 99)
        ->assertSet('sidebar', fn (array $ids): bool => $ids === ['route', 'activity'])
        ->assertDispatched('dashboard-layout-changed', grid: [], sidebar: ['route', 'activity']);
});

it('ignores a move to an unknown zone or of an unknown widget', function (): void {
    dashboardBoard()
        ->call('loadLayout', null)
        ->call('moveWidget', 'activity', 'footer', 0)
        ->call('moveWidget', 'addon-weather', 'grid', 0)
        ->assertSet('grid', fn (array $ids): bool => $ids === ['route', 'activity']);
});

it('cannot have its route changed from the browser', function (): void {
    dashboardBoard()->set('route', ['from' => ['icao' => 'KJFK']]);
})->throws(CannotUpdateLockedPropertyException::class);

it('computes the readout like skylight (JFK to LHR is about 2999 nm, bearing 51)', function (): void {
    expect(RouteReadout::distanceNm(40.6398, -73.7789, 51.47, -0.4543))->toBeBetween(2990, 3010)
        ->and(RouteReadout::bearing(40.6398, -73.7789, 51.47, -0.4543))->toBe(51);
});

it('sanitises layouts', function (): void {
    expect(WidgetCatalog::sanitize(null))->toBe(['grid' => ['route', 'activity'], 'sidebar' => []]);
});

it('formats relative times compactly', function (): void {
    $now = now();

    expect(ActivityFeed::timeAgo($now->copy()->subSeconds(20)->toIso8601String(), $now))->toBe('just now')
        ->and(ActivityFeed::timeAgo($now->copy()->subMinutes(5)->toIso8601String(), $now))->toBe('5m ago')
        ->and(ActivityFeed::timeAgo($now->copy()->subHours(3)->toIso8601String(), $now))->toBe('3h ago')
        ->and(ActivityFeed::timeAgo($now->copy()->subDays(4)->toIso8601String(), $now))->toBe('4d ago')
        ->and(ActivityFeed::timeAgo($now->copy()->subDays(70)->toIso8601String(), $now))->toBe('2mo ago')
        ->and(ActivityFeed::timeAgo($now->copy()->subDays(800)->toIso8601String(), $now))->toBe('2y ago');
});

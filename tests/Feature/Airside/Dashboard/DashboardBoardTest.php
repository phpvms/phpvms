<?php

declare(strict_types=1);

require_once __DIR__.'/../Support.php';

use App\Enums\PirepState;
use App\Features\Dashboard\Livewire\ActivityFeed;
use App\Features\Dashboard\Livewire\DashboardBoard;
use App\Features\Dashboard\Support\RouteReadout;
use App\Features\Dashboard\WidgetCatalog;
use App\Models\Pirep;
use App\Models\User;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * 7.5: the dashboard board (layout from the browser, add, remove, reset, move),
 * the widgets and the activity feed.
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

it('renders a loading state until the browser sends its layout', function (): void {
    dashboardBoard()
        ->assertSet('ready', false)
        ->assertSee('Loading dashboard')
        ->assertDontSee('Customize');
});

it('places the default widgets when the browser has no layout', function (): void {
    dashboardBoard()
        ->call('loadLayout', null)
        ->assertSet('ready', true)
        ->assertSet('grid', fn (array $ids): bool => $ids === ['route', 'activity'])
        ->assertSet('sidebar', [])
        ->assertSee('Nav display')
        ->assertSee('Customize')
        ->assertSeeHtml('data-widget="route"')
        ->assertSeeHtml('data-widget="activity"');
});

it('renders only the widgets in the layout from the browser and drops unknown ids', function (): void {
    dashboardBoard()
        ->call('loadLayout', ['grid' => ['activity', 'addon-weather', 'activity'], 'sidebar' => ['route', 5]])
        ->assertSet('grid', ['activity'])
        ->assertSet('sidebar', ['route'])
        ->assertDontSeeHtml('data-widget="addon-weather"');
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

it('shows the drag handle, remove and add controls only while editing', function (): void {
    dashboardBoard()
        ->call('loadLayout', null)
        ->assertDontSeeHtml('data-handle')
        ->assertDontSee('Add widget')
        ->assertDontSee('Reset')
        ->call('toggleEditing')
        ->assertSet('editing', true)
        ->assertSeeHtml('data-handle')
        ->assertSee('Remove widget')
        ->assertSee('Add widget')
        ->assertSee('Reset')
        ->assertSee('Done')
        ->call('toggleEditing')
        ->assertSet('editing', false)
        ->assertDontSeeHtml('data-handle');
});

it('removes a widget, offers it in the add menu, and adds it back', function (): void {
    dashboardBoard()
        ->call('loadLayout', null)
        ->call('toggleEditing')
        ->call('removeWidget', 'activity')
        ->assertSet('grid', ['route'])
        ->assertDispatched('dashboard-layout-changed', grid: ['route'], sidebar: [])
        ->assertDontSeeHtml('data-widget="activity"')
        ->assertSeeHtml("addWidget('activity')")
        ->assertDontSee('All widgets placed')
        ->call('addWidget', 'activity')
        ->assertSet('grid', fn (array $ids): bool => $ids === ['route', 'activity'])
        ->assertSee('All widgets placed');
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

it('shows the nav display note when there is no position', function (): void {
    dashboardBoard()
        ->call('loadLayout', null)
        ->assertSee('No position — set a home airport');
});

it('emits the route-map island and the TRK and DIST readout', function (): void {
    $route = [
        'from' => ['icao' => 'KJFK', 'name' => 'John F Kennedy', 'lat' => 40.6398, 'lon' => -73.7789],
        'to'   => ['icao' => 'EGLL', 'name' => 'Heathrow', 'lat' => 51.47, 'lon' => -0.4543],
    ];

    Livewire::test(DashboardBoard::class, ['route' => $route])
        ->call('loadLayout', ['grid' => ['route'], 'sidebar' => []])
        ->assertSeeHtml('data-airside-island="route-map"')
        ->assertSeeHtml('"icao":"KJFK"')
        ->assertSeeHtml('"icao":"EGLL"')
        ->assertSee('KJFK → EGLL')
        ->assertSee('TRK')
        ->assertSee(RouteReadout::bearing(40.6398, -73.7789, 51.47, -0.4543).'°')
        ->assertSee(RouteReadout::distanceNm(40.6398, -73.7789, 51.47, -0.4543).' nm');
});

it('computes the readout like skylight (JFK to LHR is about 2999 nm, bearing 51)', function (): void {
    expect(RouteReadout::distanceNm(40.6398, -73.7789, 51.47, -0.4543))->toBeBetween(2990, 3010)
        ->and(RouteReadout::bearing(40.6398, -73.7789, 51.47, -0.4543))->toBe(51);
});

it('lists the catalog and sanitises layouts', function (): void {
    expect(array_keys(WidgetCatalog::all()))->toBe(['route', 'activity'])
        ->and(WidgetCatalog::sanitize(null))->toBe(['grid' => ['route', 'activity'], 'sidebar' => []]);
});

it('loads the activity widget lazily on the dashboard board', function (): void {
    Livewire::test(DashboardBoard::class, ['route' => []])
        ->call('loadLayout', ['grid' => ['activity'], 'sidebar' => []])
        ->assertSeeHtml('wire:name="dashboard::activity-feed"');
});

it('shows the activity placeholder, then the feed', function (): void {
    $pirep = Pirep::factory()->create(['state' => PirepState::ACCEPTED, 'submitted_at' => now()->subHours(3)]);

    $component = Livewire::withoutLazyLoading()->test(ActivityFeed::class)
        ->assertSee('pilots flying now')
        ->assertSee($pirep->ident)
        ->assertSee('3h ago');

    expect($component->html())->toContain('filed');
});

it('counts pilots flying now with the singular label', function (): void {
    Pirep::factory()->create(['state' => PirepState::IN_PROGRESS]);

    Livewire::withoutLazyLoading()->test(ActivityFeed::class)->assertSee('pilot flying now');
});

it('shows the empty message when nothing happened', function (): void {
    User::query()->delete();

    Livewire::withoutLazyLoading()->test(ActivityFeed::class)->assertSee('No activity yet');
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

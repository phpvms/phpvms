<?php

declare(strict_types=1);

use App\Models\NavigationItem;
use App\Models\Page;
use App\Services\NavigationService;
use App\Services\PageService;
use Illuminate\Support\Facades\Validator;

beforeEach(function (): void {
    $this->nav = app(NavigationService::class);
});

it('returns the menu in sort order', function (): void {
    NavigationItem::factory()->create(['label' => 'Third', 'sort' => 30]);
    NavigationItem::factory()->create(['label' => 'First', 'sort' => 10]);
    NavigationItem::factory()->create(['label' => 'Second', 'sort' => 20]);

    expect(array_column($this->nav->menu(true), 'label'))->toBe(['First', 'Second', 'Third']);
});

it('nests children under their parent, in order', function (): void {
    $parent = NavigationItem::factory()->create(['label' => 'Fleet', 'sort' => 1]);
    NavigationItem::factory()->create(['label' => 'Aircraft', 'parent_id' => $parent->id, 'sort' => 2]);
    NavigationItem::factory()->create(['label' => 'Subfleets', 'parent_id' => $parent->id, 'sort' => 1]);

    $menu = $this->nav->menu(true);

    expect($menu)->toHaveCount(1)
        ->and($menu[0]['label'])->toBe('Fleet')
        ->and(array_column($menu[0]['children'], 'label'))->toBe(['Subfleets', 'Aircraft']);
});

it('hides an item from the menu while its page stays reachable', function (): void {
    $page = Page::factory()->published()->claiming('/about')->create();
    NavigationItem::factory()->forPage($page)->hidden()->create();

    expect($this->nav->menu(true))->toBe([])
        ->and(app(PageService::class)->resolve('/about')->is($page))->toBeTrue();
});

it('shows a guest only public items', function (): void {
    NavigationItem::factory()->create(['label' => 'Public']);
    NavigationItem::factory()->private()->create(['label' => 'Members']);

    expect(array_column($this->nav->menu(false), 'label'))->toBe(['Public'])
        ->and(array_column($this->nav->menu(true), 'label'))->toBe(['Public', 'Members']);
});

it('drops an item pointing at an unpublished page', function (): void {
    $page = Page::factory()->published()->claiming('/about')->create();
    NavigationItem::factory()->forPage($page)->create(['label' => 'About']);

    expect($this->nav->menu(true))->toHaveCount(1);

    app(PageService::class)->unpublish($page);

    // Never put a guaranteed 404 in the navigation.
    expect($this->nav->menu(true))->toBe([]);
});

it('drops an item pointing at a route that does not exist', function (): void {
    NavigationItem::factory()->forRoute('frontend.pilots.index')->create(['label' => 'Pilots']);
    NavigationItem::factory()->forRoute('frontend.no.such.route')->create(['label' => 'Ghost']);

    expect(array_column($this->nav->menu(true), 'label'))->toBe(['Pilots']);
});

it('keeps a dead parent that still groups live children', function (): void {
    $parent = NavigationItem::factory()->forRoute('frontend.no.such.route')->create(['label' => 'Group', 'sort' => 1]);
    NavigationItem::factory()->create(['label' => 'Child', 'parent_id' => $parent->id]);

    $menu = $this->nav->menu(true);

    expect($menu)->toHaveCount(1)
        ->and($menu[0]['label'])->toBe('Group')
        ->and($menu[0]['url'])->toBe('')
        ->and($menu[0]['children'])->toHaveCount(1);
});

it('resolves every page target in one query, not one per item', function (): void {
    $pages = Page::factory()->published()->count(5)->create();

    foreach ($pages as $page) {
        NavigationItem::factory()->forPage($page)->create();
    }

    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    $this->nav->menu(true);

    // One for the items, one for the pages.
    expect($queries)->toBe(2);
});

it('translates a label that is a translation key and shows any other as typed', function (): void {
    NavigationItem::factory()->create(['label' => 'common.dashboard', 'sort' => 1]);
    NavigationItem::factory()->create(['label' => 'Fleet', 'sort' => 2]);
    NavigationItem::factory()->create(['label' => 'Docs.v2', 'sort' => 3]);

    expect(array_column($this->nav->menu(true), 'label'))
        ->toBe([__('common.dashboard'), 'Fleet', 'Docs.v2']);
});

it('makes route and page targets in-app visits and URL targets plain links', function (): void {
    $page = Page::factory()->published()->claiming('/about')->create();
    NavigationItem::factory()->forRoute('frontend.flights.index')->create(['sort' => 1]);
    NavigationItem::factory()->forPage($page)->create(['sort' => 2]);
    NavigationItem::factory()->create(['sort' => 3]);

    expect(array_column($this->nav->menu(true), 'spa'))->toBe([true, true, false]);
});

it('passes a plain icon name through to the menu', function (): void {
    NavigationItem::factory()->create(['label' => 'Home', 'icon' => 'i-tabler-plane']);

    expect($this->nav->menu(true)[0]['icon'])->toBe('i-tabler-plane');
});

it('drops an icon that is markup rather than a name', function (): void {
    NavigationItem::factory()->create(['label' => 'Home', 'icon' => '<svg onload="alert(1)"></svg>']);

    expect($this->nav->menu(true)[0]['icon'])->toBeNull();
});

it('rejects markup in the menu item form icon rule', function (): void {
    $rules = ['icon' => ['string', 'regex:'.NavigationService::ICON_PATTERN]];

    expect(Validator::make(['icon' => '<svg onload="x"></svg>'], $rules)->fails())->toBeTrue()
        ->and(Validator::make(['icon' => 'PlaneArrival'], $rules)->passes())->toBeTrue()
        ->and(Validator::make(['icon' => 'layout-dashboard'], $rules)->passes())->toBeTrue();
});

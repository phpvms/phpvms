<?php

declare(strict_types=1);

require_once __DIR__.'/Support.php';

use App\Features\Tiles\Tile;
use App\Features\Tiles\Tiles;
use App\Features\Tiles\TilesServiceProvider;

// Start from an empty registry; the first-party element tiles are checked in their own test.
beforeEach(fn () => app()->instance(Tiles::class, new Tiles()));

it('builds once to read the id, then again only when the registry is read', function (): void {
    $built = 0;
    $registry = app(Tiles::class);
    $registry->register(function () use (&$built): Tile {
        $built++;

        return dashboardTile('alpha');
    });

    expect($registry->has('alpha'))->toBeTrue()
        ->and($built)->toBe(1);

    $registry->all();
    $registry->find('alpha');

    expect($built)->toBe(2);
});

it('builds tiles again for each locale', function (): void {
    app(Tiles::class)->register(fn (): Tile => Tile::livewire(
        'alpha', 'x::alpha', app()->getLocale() === 'fr' ? 'Bonjour' : 'Hello', 'star',
    ));
    $registry = app(Tiles::class);

    app()->setLocale('en');
    $english = $registry->find('alpha')?->title;
    app()->setLocale('fr');
    $french = $registry->find('alpha')?->title;

    expect([$english, $french])->toBe(['Hello', 'Bonjour']);
});

it('lets the last registration override a duplicate id', function (): void {
    registerTiles(dashboardTile('alpha'), dashboardTile('beta'));
    registerTiles(Tile::view('alpha', 'x::override', 'Override', 'star'));

    $registry = app(Tiles::class);

    expect(array_keys($registry->all()))->toBe(['beta', 'alpha'])
        ->and($registry->find('alpha')?->view)->toBe('x::override')
        ->and($registry->find('missing'))->toBeNull();
});

it('removes a tile whether it is registered before or after the removal', function (): void {
    $registry = app(Tiles::class);
    registerTiles(dashboardTile('early'));

    $registry->remove('early');
    $registry->remove('late');
    registerTiles(dashboardTile('late'), dashboardTile('kept'));

    expect(array_keys($registry->all()))->toBe(['kept'])
        ->and($registry->has('late'))->toBeFalse();
});

it('indexes a tile by its own id and replaces a duplicate', function (): void {
    $registry = app(Tiles::class);
    $registry->register(fn (): Tile => dashboardTile('alpha'));
    $registry->register(fn (): Tile => Tile::view('alpha', 'x::second', 'Second', 'star'));

    expect($registry->has('alpha'))->toBeTrue()
        ->and($registry->find('alpha')?->view)->toBe('x::second')
        ->and(array_keys($registry->all()))->toBe(['alpha']);

    $registry->remove('alpha');

    expect($registry->has('alpha'))->toBeFalse()
        ->and($registry->find('alpha'))->toBeNull();
});

it('accepts a numeric id', function (): void {
    registerTiles(dashboardTile('123'), dashboardTile('alpha'));

    $registry = app(Tiles::class);

    expect($registry->find('123')?->id)->toBe('123')
        ->and($registry->find('alpha')?->id)->toBe('alpha');
});

it('builds the default layout by zone, defaults only, sorted by order', function (): void {
    registerTiles(
        dashboardTile('late', order: 50),
        dashboardTile('first', order: 10),
        dashboardTile('tie-a', order: 20),
        dashboardTile('optional', default: false),
        dashboardTile('side', zone: 'sidebar'),
        dashboardTile('tie-b', order: 20),
    );

    expect(app(Tiles::class)->defaultLayout())->toBe([
        'grid'    => ['first', 'tie-a', 'tie-b', 'late'],
        'sidebar' => ['side'],
    ]);
});

it('sanitizes a saved layout', function (): void {
    registerTiles(
        dashboardTile('alpha'),
        dashboardTile('beta', zone: 'sidebar'),
        dashboardTile('gamma', default: false),
    );
    $registry = app(Tiles::class);

    expect($registry->sanitize([
        'grid'    => ['gamma', 'unknown', 'gamma', 42, 'alpha'],
        'sidebar' => ['alpha', 'beta'],
    ]))->toBe(['grid' => ['gamma', 'alpha'], 'sidebar' => ['beta']]);

    // A tile stays in the zone it was saved in, even if declared for another.
    expect($registry->sanitize(['grid' => ['beta'], 'sidebar' => []]))
        ->toBe(['grid' => ['beta'], 'sidebar' => []]);

    foreach (['junk', null, 5, [], ['other' => ['alpha']], ['grid' => 'corrupt', 'sidebar' => []]] as $garbage) {
        expect($registry->sanitize($garbage))->toBe($registry->defaultLayout());
    }
});

it('keeps tiles not shown in the dashboard out of every dashboard method', function (): void {
    registerTiles(
        dashboardTile('alpha'),
        Tile::livewire('profile-only', 'x::profile-only', 'Profile only', 'star'),
    );
    $registry = app(Tiles::class);

    expect(array_keys($registry->all()))->toBe(['alpha', 'profile-only'])
        ->and(array_keys($registry->dashboard()))->toBe(['alpha'])
        ->and($registry->sanitize(['grid' => ['profile-only', 'alpha'], 'sidebar' => []]))->toBe(['grid' => ['alpha'], 'sidebar' => []])
        ->and(array_map(fn (Tile $tile): string => $tile->id, $registry->zone(['grid' => ['profile-only', 'alpha']], 'grid')))->toBe(['alpha'])
        ->and($registry->available(['grid' => [], 'sidebar' => []]))->toHaveCount(1)
        ->and($registry->find('profile-only'))->not->toBeNull();
});

it('rejects invalid definitions', function (callable $make): void {
    expect($make)->toThrow(InvalidArgumentException::class);
})->with([
    'bad id'                => [fn (): Tile => Tile::livewire('Bad_Id', 'x::y', 'T', 'star')],
    'bad zone'              => [fn (): Tile => Tile::livewire('ok', 'x::y', 'T', 'star', zone: 'footer')],
    'bad span'              => [fn (): Tile => Tile::livewire('ok', 'x::y', 'T', 'star', span: 3)],
    'empty view'            => [fn (): Tile => Tile::view('ok', '', 'T', 'star')],
    'empty component'       => [fn (): Tile => Tile::livewire('ok', ' ', 'T', 'star')],
    'default off-dashboard' => [fn (): Tile => Tile::livewire('ok', 'x::y', 'T', 'star', default: true)],
    'empty id'              => [fn (): Tile => Tile::element('', 'pv-x', '/x.js', 'T', 'star')],
    'element bad zone'      => [fn (): Tile => Tile::element('ok', 'pv-x', '/x.js', 'T', 'star', zone: 'footer')],
    'element empty tag'     => [fn (): Tile => Tile::element('ok', '', '/x.js', 'T', 'star')],
    'element plain tag'     => [fn (): Tile => Tile::element('ok', 'div', '/x.js', 'T', 'star')],
    'element empty module'  => [fn (): Tile => Tile::element('ok', 'pv-x', ' ', 'T', 'star')],
    'array attribute'       => [fn (): Tile => Tile::element('ok', 'pv-x', '/x.js', 'T', 'star', ['items' => ['a']])],
    'null attribute'        => [fn (): Tile => Tile::element('ok', 'pv-x', '/x.js', 'T', 'star', ['heading' => null])],
    'bad attribute name'    => [fn (): Tile => Tile::element('ok', 'pv-x', '/x.js', 'T', 'star', ['a b="x' => 'y'])],
]);

it('reports the kind of each tile, with exactly one render target set', function (): void {
    $livewire = Tile::livewire('a', 'x::a', 'A', 'star');
    $view = Tile::view('b', 'x::b', 'B', 'star');
    $element = Tile::element('c', 'pv-c', '/c.js', 'C', 'star', ['n' => 1, 'on' => true]);

    expect([$livewire->kind(), $view->kind(), $element->kind()])->toBe(['livewire', 'view', 'element'])
        ->and(array_map(fn (Tile $t): int => count(array_filter([$t->livewire, $t->view, $t->tag])), [$livewire, $view, $element]))->toBe([1, 1, 1])
        ->and($element->module)->toBe('/c.js')
        ->and($element->attributes)->toBe(['n' => 1, 'on' => true]);
});

it('registers the first-party element tiles outside the dashboard', function (): void {
    app()->forgetInstance(Tiles::class);
    $registry = app(Tiles::class);
    new TilesServiceProvider(app())->boot();

    foreach (['pv-hero', 'pv-callout', 'pv-stat-group'] as $id) {
        $tile = $registry->find($id);

        expect($tile)->not->toBeNull()
            ->and($tile?->kind())->toBe('element')
            ->and($tile?->tag)->toBe($id)
            ->and($tile?->module)->toBe('/build/skylight/elements.js')
            ->and($registry->dashboard())->not->toHaveKey($id);
    }
});

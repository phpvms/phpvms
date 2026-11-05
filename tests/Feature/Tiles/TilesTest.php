<?php

declare(strict_types=1);

require_once __DIR__.'/Support.php';

use App\Features\Tiles\Tile;
use App\Features\Tiles\Tiles;

it('builds tiles only when the registry is read', function (): void {
    $built = 0;
    app(Tiles::class)->register('alpha', function () use (&$built): Tile {
        $built++;

        return dashboardTile('alpha');
    });
    $registry = app(Tiles::class);

    expect($registry->has('alpha'))->toBeTrue()
        ->and($built)->toBe(0);

    $registry->all();
    $registry->find('alpha');

    expect($built)->toBe(1);
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
    $registry->all();

    $registry->remove('early');
    $registry->remove('late');
    registerTiles(dashboardTile('late'), dashboardTile('kept'));

    expect(array_keys($registry->all()))->toBe(['kept'])
        ->and($registry->has('late'))->toBeFalse();
});

it('rejects a closure that builds a tile with another id', function (): void {
    app(Tiles::class)->register('alpha', fn (): Tile => dashboardTile('beta'));

    expect(fn () => app(Tiles::class)->all())->toThrow(InvalidArgumentException::class);
});

it('accepts a numeric id', function (): void {
    registerTiles(dashboardTile('123'), dashboardTile('alpha'));

    $registry = app(Tiles::class);

    expect($registry->find('123')?->id)->toBe('123')
        ->and($registry->find('alpha')?->id)->toBe('alpha');
});

it('builds tiles again for each locale', function (): void {
    app(Tiles::class)->register('alpha', fn (): Tile => Tile::livewire(
        'alpha', 'x::alpha', app()->getLocale() === 'fr' ? 'Bonjour' : 'Hello', 'star',
    ));
    $registry = app(Tiles::class);

    app()->setLocale('en');
    $english = $registry->find('alpha')?->title;
    app()->setLocale('fr');
    $french = $registry->find('alpha')?->title;

    expect([$english, $french])->toBe(['Hello', 'Bonjour']);
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
]);

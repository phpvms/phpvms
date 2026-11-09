<?php

declare(strict_types=1);

require_once __DIR__.'/Support.php';

use App\Features\Tiles\Tile;
use App\Features\Tiles\Tiles;
use App\View\Components\Tile as TileComponent;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Livewire\Component;
use Livewire\Livewire;

class MountProbe extends Component
{
    /** @var list<string> */
    public static array $mounted = [];

    /** @var array<string, mixed> */
    public static array $forwarded = [];

    public string $airline = '';

    public function mount(string $route = ''): void
    {
        self::$mounted[] = $route;
        self::$forwarded = $this->getHtmlAttributes();
    }

    public function render(): string
    {
        return '<div>probe</div>';
    }
}

beforeEach(function (): void {
    app()->instance(Tiles::class, new Tiles());
    View::addNamespace('probe', __DIR__.'/views');
    MountProbe::$mounted = [];
    MountProbe::$forwarded = [];
});

it('resolves ids in order and skips unknown ones', function (): void {
    registerTiles(dashboardTile('alpha'), dashboardTile('beta'), dashboardTile('gamma'));

    $ids = array_map(
        fn (Tile $w): string => $w->id,
        app(Tiles::class)->resolve(['gamma', 'nope', 'alpha']),
    );

    expect($ids)->toBe(['gamma', 'alpha']);
});

it('returns a zone and the unplaced tiles in catalog order', function (): void {
    registerTiles(
        dashboardTile('alpha'),
        dashboardTile('beta', zone: 'sidebar'),
        dashboardTile('gamma', default: false),
        dashboardTile('delta', default: false),
    );
    $registry = app(Tiles::class);
    $layout = ['grid' => ['alpha'], 'sidebar' => ['beta']];

    $ids = fn (array $tiles): array => array_map(fn (Tile $w): string => $w->id, $tiles);

    expect($ids($registry->zone($layout, 'sidebar')))->toBe(['beta'])
        ->and($ids($registry->zone(['grid' => ['alpha']], 'sidebar')))->toBe([])
        ->and($ids($registry->available($layout)))->toBe(['gamma', 'delta'])
        ->and($ids($registry->available(['grid' => [], 'sidebar' => []])))->toBe(['alpha', 'beta', 'gamma', 'delta']);
});

it('lets render-time data override declared data for view tiles only', function (): void {
    $view = Tile::view('news', 'probe::probe', 'News', 'star', data: ['label' => 'declared', 'limit' => 5]);
    $livewire = Tile::livewire('stats', 'x::stats', 'Stats', 'star');

    expect($view->dataWith(['label' => 'runtime']))->toBe(['label' => 'runtime', 'limit' => 5])
        ->and($view->dataWith())->toBe(['label' => 'declared', 'limit' => 5])
        ->and($livewire->dataWith(['route' => 'EGLL']))->toBe(['route' => 'EGLL']);
});

it('renders a view tile from its view with the merged data', function (): void {
    registerTiles(Tile::view('news', 'probe::probe', 'News', 'star', data: ['label' => 'declared', 'limit' => 5]));

    $html = Blade::render('<x-tile id="news" :with="[\'label\' => \'runtime\']" />');

    expect(trim($html))->toBe('probe:runtime:5');
});

it('mounts the Livewire component of a livewire tile with the render-time data', function (): void {
    Livewire::component('probe-widget', MountProbe::class);
    $tile = Tile::livewire('stats', 'probe-widget', 'Stats', 'star');

    Blade::render('<x-tile :tile="$tile" :with="[\'route\' => \'EGLL\']" />', ['tile' => $tile]);

    expect(MountProbe::$mounted)->toBe(['EGLL']);
});

it('passes a livewire tile only the params it accepts', function (): void {
    Livewire::component('probe-widget', MountProbe::class);
    $tile = Tile::livewire('stats', 'probe-widget', 'Stats', 'star');

    Blade::render(
        '<x-tile :tile="$tile" :with="$with" />',
        ['tile' => $tile, 'with' => ['route' => 'EGLL', 'airline' => 'KLM', 'pilot-email' => 'secret@example.com']],
    );

    expect(MountProbe::$mounted)->toBe(['EGLL'])
        ->and(MountProbe::$forwarded)->toBe([]);
});

it('keeps livewire control params such as lazy', function (): void {
    Livewire::component('probe-widget', MountProbe::class);
    $tile = Tile::livewire('stats', 'probe-widget', 'Stats', 'star');

    Blade::render('<x-tile :tile="$tile" :with="[\'lazy\' => true]" />', ['tile' => $tile]);

    expect(MountProbe::$mounted)->toBe([]);
});

it('renders nothing for an unknown id, or a comment naming it in debug', function (): void {
    config(['app.debug' => false]);
    expect(trim(Blade::render('<x-tile id="missing" />')))->toBe('');

    config(['app.debug' => true]);
    expect(Blade::render('<x-tile id="missing" />'))->toContain('missing');
});

it('rejects tile and id together, or neither', function (): void {
    $tile = dashboardTile('alpha');

    expect(fn (): TileComponent => new TileComponent(tile: $tile, id: 'alpha'))->toThrow(InvalidArgumentException::class)
        ->and(fn (): TileComponent => new TileComponent())->toThrow(InvalidArgumentException::class);
});

it('renders element tiles with one module script however many share it', function (): void {
    $one = Tile::element('one', 'pv-one', '/build/shared.js', 'One', 'star', ['heading' => 'Hi']);
    $two = Tile::element('two', 'pv-two', '/build/shared.js', 'Two', 'star');

    $html = Blade::render('<x-tile :tile="$one" /><x-tile :tile="$two" />', ['one' => $one, 'two' => $two]);

    expect(substr_count($html, '<script type="module" src="/build/shared.js"></script>'))->toBe(1)
        ->and($html)->toContain('<pv-one heading="Hi"></pv-one>')
        ->and($html)->toContain('<pv-two></pv-two>');
});

it('escapes element attribute values and writes booleans by presence', function (): void {
    $tile = Tile::element('one', 'pv-one', '/m.js', 'One', 'star', [
        'heading' => 'say "hi" <b>',
        'on'      => true,
        'off'     => false,
        'count'   => 3,
    ]);

    $html = Blade::render('<x-tile :tile="$tile" />', ['tile' => $tile]);

    expect($html)->toContain('heading="say &quot;hi&quot; &lt;b&gt;"')
        ->and($html)->toContain(' on')
        ->and($html)->toContain('count="3"')
        ->and($html)->not->toContain('off');
});

<?php

declare(strict_types=1);

require_once __DIR__.'/Support.php';

use App\Features\Dashboard\DashboardWidget;
use App\Features\Dashboard\DashboardWidgets;
use App\View\Components\Dashboard\Widget;
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
    View::addNamespace('probe', __DIR__.'/views');
    MountProbe::$mounted = [];
    MountProbe::$forwarded = [];
});

it('resolves ids in order and skips unknown ones', function (): void {
    registerWidgets(gridWidget('alpha'), gridWidget('beta'), gridWidget('gamma'));

    $ids = array_map(
        fn (DashboardWidget $w): string => $w->id,
        app(DashboardWidgets::class)->resolve(['gamma', 'nope', 'alpha']),
    );

    expect($ids)->toBe(['gamma', 'alpha']);
});

it('returns a zone and the unplaced widgets in catalog order', function (): void {
    registerWidgets(
        gridWidget('alpha'),
        gridWidget('beta', zone: 'sidebar'),
        gridWidget('gamma', default: false),
        gridWidget('delta', default: false),
    );
    $registry = app(DashboardWidgets::class);
    $layout = ['grid' => ['alpha'], 'sidebar' => ['beta']];

    $ids = fn (array $widgets): array => array_map(fn (DashboardWidget $w): string => $w->id, $widgets);

    expect($ids($registry->zone($layout, 'sidebar')))->toBe(['beta'])
        ->and($ids($registry->zone(['grid' => ['alpha']], 'sidebar')))->toBe([])
        ->and($ids($registry->available($layout)))->toBe(['gamma', 'delta'])
        ->and($ids($registry->available(['grid' => [], 'sidebar' => []])))->toBe(['alpha', 'beta', 'gamma', 'delta']);
});

it('lets render-time data override declared data for view widgets only', function (): void {
    $view = DashboardWidget::view('news', 'probe::probe', 'News', 'star', data: ['label' => 'declared', 'limit' => 5]);
    $livewire = DashboardWidget::livewire('stats', 'x::stats', 'Stats', 'star');

    expect($view->dataWith(['label' => 'runtime']))->toBe(['label' => 'runtime', 'limit' => 5])
        ->and($view->dataWith())->toBe(['label' => 'declared', 'limit' => 5])
        ->and($livewire->dataWith(['route' => 'EGLL']))->toBe(['route' => 'EGLL']);
});

it('renders a view widget from its view with the merged data', function (): void {
    registerWidgets(DashboardWidget::view('news', 'probe::probe', 'News', 'star', data: ['label' => 'declared', 'limit' => 5]));

    $html = Blade::render('<x-dashboard.widget id="news" :with="[\'label\' => \'runtime\']" />');

    expect(trim($html))->toBe('probe:runtime:5');
});

it('mounts the Livewire component of a livewire widget with the render-time data', function (): void {
    Livewire::component('probe-widget', MountProbe::class);
    $widget = DashboardWidget::livewire('stats', 'probe-widget', 'Stats', 'star');

    Blade::render('<x-dashboard.widget :widget="$widget" :with="[\'route\' => \'EGLL\']" />', ['widget' => $widget]);

    expect(MountProbe::$mounted)->toBe(['EGLL']);
});

it('passes a livewire widget only the params it accepts', function (): void {
    Livewire::component('probe-widget', MountProbe::class);
    $widget = DashboardWidget::livewire('stats', 'probe-widget', 'Stats', 'star');

    Blade::render(
        '<x-dashboard.widget :widget="$widget" :with="$with" />',
        ['widget' => $widget, 'with' => ['route' => 'EGLL', 'airline' => 'KLM', 'pilot-email' => 'secret@example.com']],
    );

    expect(MountProbe::$mounted)->toBe(['EGLL'])
        ->and(MountProbe::$forwarded)->toBe([]);
});

it('keeps livewire control params such as lazy', function (): void {
    Livewire::component('probe-widget', MountProbe::class);
    $widget = DashboardWidget::livewire('stats', 'probe-widget', 'Stats', 'star');

    Blade::render('<x-dashboard.widget :widget="$widget" :with="[\'lazy\' => true]" />', ['widget' => $widget]);

    expect(MountProbe::$mounted)->toBe([]);
});

it('renders nothing for an unknown id, or a comment naming it in debug', function (): void {
    config(['app.debug' => false]);
    expect(trim(Blade::render('<x-dashboard.widget id="missing" />')))->toBe('');

    config(['app.debug' => true]);
    expect(Blade::render('<x-dashboard.widget id="missing" />'))->toContain('missing');
});

it('rejects widget and id together, or neither', function (): void {
    $widget = gridWidget('alpha');

    expect(fn (): Widget => new Widget(widget: $widget, id: 'alpha'))->toThrow(InvalidArgumentException::class)
        ->and(fn (): Widget => new Widget())->toThrow(InvalidArgumentException::class);
});

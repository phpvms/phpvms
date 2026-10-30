<?php

declare(strict_types=1);

require_once __DIR__.'/../Support.php';

use App\Contracts\Metar as MetarProvider;
use App\Models\Award;
use App\Models\News;
use App\Models\Pirep;
use App\Models\User;
use App\Models\UserAward;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;

/**
 * 8.3: error pages, the pagination views and the app/Widgets views, with airside active.
 */
beforeEach(function (): void {
    useAirside();
    $this->withoutVite();
});

it('renders the 404 page in the airside layout with status 404', function (): void {
    $this->get('/there-is-no-such-page')
        ->assertNotFound()
        ->assertSee('class="pv-error', false)
        ->assertSee(__('errors.404.title'))
        ->assertSee('x-data="tallstackui_layout()"', false)
        ->assertDontSee('bootstrap', false);
});

it('renders the 401 page in the airside layout with status 401', function (): void {
    Route::middleware('web')->get('/airside-probe/forbidden', fn () => abort(401));

    $this->get('/airside-probe/forbidden')
        ->assertUnauthorized()
        ->assertSee('class="pv-error', false)
        ->assertSee(__('errors.401.title'));
});

it('renders the 500 page without the layout, the database or a build manifest', function (): void {
    Route::middleware('web')->get('/airside-probe/broken', fn () => abort(500));

    $this->get('/airside-probe/broken')
        ->assertStatus(500)
        ->assertSee('For administrators')
        ->assertDontSee('pv-region-nav', false)
        ->assertDontSee('/build/', false);
});

it('renders the 503 maintenance page without the layout', function (): void {
    Route::middleware('web')->get('/airside-probe/maintenance', fn () => abort(503));

    $this->get('/airside-probe/maintenance')
        ->assertStatus(503)
        ->assertSee('We will be right back')
        ->assertDontSee('pv-region-nav', false);
});

it('renders the pagination views in airside markup', function (): void {
    User::factory()->count(3)->create();
    $paginator = User::query()->orderBy('id')->paginate(1, ['*'], 'page', 2);

    foreach (['pagination.default', 'pagination.bootstrap-5'] as $view) {
        $html = Blade::render('{{ $paginator->links($view) }}', ['paginator' => $paginator, 'view' => $view]);

        expect($html)->toContain('pv-pagination')->toContain('aria-current="page"')->not->toContain('class="pagination');
    }

    expect(Blade::render("{{ \$paginator->links('pagination.simple-default') }}", ['paginator' => User::query()->simplePaginate(1)]))->toContain('pv-pagination');
});

it('renders the latest pilots widget', function (): void {
    $user = User::factory()->create(['name' => 'Widget Pilot']);

    $html = Blade::render('{{ Widget::LatestPilots() }}');

    expect($html)->toContain('pv-widget')->toContain($user->ident);
});

it('renders the latest pireps widget', function (): void {
    $pirep = Pirep::factory()->create();

    $html = Blade::render('{{ Widget::LatestPireps() }}');

    expect($html)->toContain('pv-widget')->toContain($pirep->dpt_airport_id);
});

it('renders the latest news widget with and without news', function (): void {
    expect(Blade::render('{{ Widget::LatestNews() }}'))->toContain(__('widgets.latestnews.nonewsfound'));

    News::factory()->create(['subject' => 'Runway reopened', 'body' => '<p>Open again</p>']);

    expect(Blade::render('{{ Widget::LatestNews() }}'))->toContain('Runway reopened')->toContain('<p>Open again</p>');
});

it('renders the latest awards widget', function (): void {
    $user = User::factory()->create();
    $award = Award::factory()->create(['name' => 'Century club']);
    UserAward::create(['user_id' => $user->id, 'award_id' => $award->id]);

    expect(Blade::render('{{ Widget::LatestAwards() }}'))->toContain('Century club')->toContain($user->ident);
});

it('renders the weather widget with a decoded report and without one', function (): void {
    $fake = fn (string $metar) => app()->instance(MetarProvider::class, new class($metar) extends MetarProvider
    {
        public function __construct(private readonly string $raw) {}

        public function metar(string $icao): string
        {
            return $this->raw;
        }

        public function taf(string $icao): string
        {
            return '';
        }

        protected function get_metar($icao): string
        {
            return $this->raw;
        }

        protected function get_taf($icao): string
        {
            return '';
        }
    });

    $fake('');
    expect(Blade::render("{{ Widget::Weather(['icao' => 'KJFK']) }}"))->toContain(__('widgets.weather.nometar'));

    $fake('KJFK 301751Z 28012KT 10SM FEW050 18/09 A3012');
    $html = Blade::render("{{ Widget::Weather(['icao' => 'KJFK']) }}");

    expect($html)->toContain('KJFK 301751Z 28012KT')->toContain(__('widgets.weather.showdecoded'))->toContain(__('widgets.weather.wind'));
});

it('renders the airspace map widget as a route map island with one airport', function (): void {
    $html = Blade::render("{{ Widget::AirspaceMap(['width' => '100%', 'height' => '400px', 'lat' => 51.5, 'lon' => -0.4]) }}");

    expect($html)->toContain('data-airside-island="route-map"')->toContain('"to":null')->toContain('51.5');
});

it('renders the live map widget as a live map island', function (): void {
    $html = Blade::render('{{ Widget::LiveMap() }}');

    expect($html)->toContain('data-airside-island="live-map"')->toContain('data-live-list')->toContain('initialCenter');
});

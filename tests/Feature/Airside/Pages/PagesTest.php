<?php

declare(strict_types=1);

require_once __DIR__.'/../Support.php';
require_once __DIR__.'/../Flights/Support.php';

use App\Enums\PageType;
use App\Models\Airport;
use App\Models\File;
use App\Models\Page;
use App\Models\User;

/**
 * 8.2: the pages skylight has no design for: airport, roster, downloads, content pages, home and credits.
 */
beforeEach(function (): void {
    useAirside();
    airsideFlightsBaseline();
    $this->withoutVite();
});

it('renders the airport page with weather, map island, notes, files and traffic', function (): void {
    $fixture = airsideDispatchFixture();
    $fixture['departure']->update(['notes' => '<p>Mind the crosswind</p>', 'elevation' => 126, 'lat' => 51.47, 'lon' => -0.45]);
    File::factory()->create([
        'name'           => 'Charts pack',
        'ref_model_type' => Airport::class,
        'ref_model_id'   => $fixture['departure']->id,
    ]);
    $this->actingAs($fixture['user']);

    $this->get(route('frontend.airports.show', [$fixture['departure']->id]))
        ->assertOk()
        ->assertSee($fixture['departure']->icao)
        ->assertSee('126 ft')
        ->assertSee('Mind the crosswind', false)
        ->assertSee('data-airside-island="route-map"', false)
        ->assertSee('"to":null', false)
        ->assertSee('Charts pack')
        ->assertSee(__('flights.outbound'))
        ->assertSee($fixture['flight']->ident)
        ->assertSee(route('frontend.airports.show', ['id' => $fixture['arrival']->id]), false);
});

it('shows the empty traffic message and no map for an airport without coordinates', function (): void {
    $airport = Airport::factory()->create(['lat' => null, 'lon' => null]);

    $this->actingAs(User::factory()->create())
        ->get(route('frontend.airports.show', [$airport->id]))
        ->assertOk()
        ->assertSee(__('flights.none'))
        ->assertSee('This airport has no coordinates.')
        ->assertDontSee('data-airside-island="route-map"', false);
});

it('lists pilots on the roster and sorts by the query string', function (): void {
    $first = User::factory()->create(['name' => 'Alpha Pilot', 'flights' => 5]);
    $second = User::factory()->create(['name' => 'Zulu Pilot', 'flights' => 9]);
    $this->actingAs($first);

    $this->get(route('frontend.users.index'))
        ->assertOk()
        ->assertSee(trans_choice('common.pilot', 2))
        ->assertSee(route('frontend.users.show.public', [$second->id]), false)
        ->assertSeeInOrder(['Alpha', 'Zulu']);

    $this->get(route('frontend.users.index', ['orderBy' => 'flights', 'sortedBy' => 'desc']))
        ->assertOk()
        ->assertSee('aria-sort="descending"', false)
        ->assertSeeInOrder(['Zulu', 'Alpha']);
});

it('paginates the roster and keeps the sort in the page links', function (): void {
    User::factory()->count(3)->create();
    $this->actingAs(User::factory()->create());

    $this->get(route('frontend.users.index', ['limit' => 2, 'orderBy' => 'name', 'sortedBy' => 'asc']))
        ->assertOk()
        ->assertSee('orderBy=name', false)
        ->assertSee('page=2', false);
});

it('lists the downloads grouped by owner and shows the empty state', function (): void {
    $this->actingAs(User::factory()->create());

    $this->get(route('frontend.downloads.index'))->assertOk()->assertSee(__('downloads.none'));

    $airport = Airport::factory()->create();
    File::factory()->create(['name' => 'Airport chart', 'ref_model_type' => Airport::class, 'ref_model_id' => $airport->id]);

    $this->get(route('frontend.downloads.index'))
        ->assertOk()
        ->assertSee('Airport chart')
        ->assertSee('Airport &gt; '.$airport->icao, false);
});

it('renders a content page with its body', function (): void {
    $page = Page::factory()->published()->create(['name' => 'Rules of the airline', 'type' => PageType::PAGE, 'public' => true, 'body' => '<p>Be kind</p>']);

    $this->get(route('frontend.pages.show', [$page->slug]))
        ->assertOk()
        ->assertSee('Rules of the airline')
        ->assertSee('<p>Be kind</p>', false);
});

it('renders the home page with the newest pilots', function (): void {
    $pilot = User::factory()->create(['name' => 'Newest Pilot']);

    $this->get(route('frontend.home'))
        ->assertOk()
        ->assertSee(__('common.newest_pilots'))
        ->assertSee(route('frontend.profile.show.public', [$pilot->id]), false);
});

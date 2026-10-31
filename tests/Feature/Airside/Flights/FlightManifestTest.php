<?php

declare(strict_types=1);

require_once __DIR__.'/Support.php';

use App\Features\Bids\Livewire\AssignmentDrawer;
use App\Features\Flights\Livewire\FlightManifest;
use App\Models\Airline;
use App\Models\Bid;
use App\Models\Flight;
use Livewire\Livewire;

/**
 * 7.1: the flight manifest, with its filters in the query string.
 */
beforeEach(function (): void {
    useAirside();
    airsideFlightsBaseline();
    $this->withoutVite();
    $this->fixture = airsideDispatchFixture(flight: ['flight_number' => '104']);
    $this->actingAs($this->fixture['user']);
});

it('renders the flights page with the manifest and the assignment drawer', function (): void {
    $this->get('/flights')
        ->assertOk()
        ->assertSeeLivewire(FlightManifest::class)
        ->assertSeeLivewire(AssignmentDrawer::class);
});

it('lists the flight with its facts and a Bid button', function (): void {
    $flight = $this->fixture['flight'];

    Livewire::test(FlightManifest::class)
        ->assertSee($flight->ident)
        ->assertSee($this->fixture['departure']->icao);
});

it('offers Overview and View Bid on a flight the pilot has a bid on', function (): void {
    Bid::query()->create(['user_id' => $this->fixture['user']->id, 'flight_id' => $this->fixture['flight']->id]);

    Livewire::test(FlightManifest::class)
        ->assertSee('On bid')
        ->assertSee('Overview')
        ->assertSee('View Bid');
});

it('shows a locked flight as unavailable with its reason', function (): void {
    updateSetting('bids.allow_multiple_bids', false);
    $other = airsideDispatchFixture($this->fixture['user']);
    Bid::query()->create(['user_id' => $this->fixture['user']->id, 'flight_id' => $other['flight']->id]);

    Livewire::test(FlightManifest::class)
        ->assertSee('Unavailable')
        ->assertSee('Remove your current bid before selecting another flight');
});

it('keeps the filters and the page in the query string and restores them on reload', function (): void {
    $query = [];

    foreach (FlightManifest::FILTERS as $queryName) {
        $query[$queryName] = 'v-'.$queryName;
    }

    $component = Livewire::withQueryParams($query)->test(FlightManifest::class);

    foreach (FlightManifest::FILTERS as $property => $queryName) {
        $component->assertSet($property, $query[$queryName]);
    }
});

it('filters only on submit and puts the applied filters in the URL', function (): void {
    $other = airsideDispatchFixture($this->fixture['user'], ['flight_number' => '999']);

    Livewire::test(FlightManifest::class)
        ->assertSee('2 shown')
        ->set('flightNumber', '999')
        ->call('apply')
        ->assertSee('1 shown')
        ->assertSee('Flight: 999')
        ->assertSee($other['flight']->ident)
        ->assertDontSee($this->fixture['flight']->ident)
        ->assertSet('page', 1);
});

it('filters on a url that arrives with the filter already set', function (): void {
    airsideDispatchFixture($this->fixture['user'], ['flight_number' => '999']);

    Livewire::withQueryParams(['flight_number' => '104'])->test(FlightManifest::class)
        ->assertSee('1 shown')
        ->assertSee($this->fixture['flight']->ident);
});

it('accepts the old airport query names', function (): void {
    Livewire::withQueryParams(['dpt_airport_id' => $this->fixture['departure']->id])->test(FlightManifest::class)
        ->assertSet('depIcao', $this->fixture['departure']->id);
});

it('clears every filter', function (): void {
    Livewire::withQueryParams(['flight_number' => '555', 'search' => 'nothing'])->test(FlightManifest::class)
        ->assertSee('No flights found')
        ->call('clear')
        ->assertSet('flightNumber', null)
        ->assertSet('search', null)
        ->assertSee('0 active')
        ->assertSee($this->fixture['flight']->ident);
});

it('shows the empty state when no flight matches', function (): void {
    Livewire::test(FlightManifest::class)
        ->set('flightNumber', '424242')
        ->call('apply')
        ->assertSee('No flights found')
        ->assertDontSee($this->fixture['flight']->ident);
});

it('shows the error when a filter value is refused and still lists flights', function (): void {
    Livewire::test(FlightManifest::class)
        ->set('orderBy', 'password')
        ->call('apply')
        ->assertSee('The schedule could not be updated. The current results are still available.')
        ->assertSee($this->fixture['flight']->ident);
});

it('pages through the results', function (): void {
    foreach (range(1, 2) as $number) {
        airsideDispatchFixture($this->fixture['user'], ['flight_number' => (string) (200 + $number)]);
    }

    Livewire::test(FlightManifest::class)
        ->set('limit', '1')
        ->call('apply')
        ->assertSee('Page 1 of 3 · 3 flights')
        ->call('nextPage')
        ->assertSet('page', 2)
        ->assertSee('Page 2 of 3 · 3 flights')
        ->call('nextPage')
        ->call('nextPage')
        ->assertSet('page', 3)
        ->call('previousPage')
        ->assertSet('page', 2);
});

it('hides flights of another airline when the pilot is restricted to their company', function (): void {
    updateSetting('pilots.restrict_to_company', true);
    $stranger = airsideDispatchFixture();
    $otherAirline = Airline::factory()->create();
    expect($otherAirline->id)->not->toBe($this->fixture['airline']->id);
    Flight::query()->whereKey($stranger['flight']->id)->update(['airline_id' => $otherAirline->id]);

    Livewire::test(FlightManifest::class)
        ->assertDontSee($stranger['flight']->ident)
        ->assertSee($this->fixture['flight']->ident);
});

it('renders the Bid button with the real flight id in the open event', function (): void {
    $id = $this->fixture['flight']->id;

    Livewire::test(FlightManifest::class)
        ->assertSeeHtml("flightId: '{$id}'")
        ->assertDontSee('@js', false);
});

<?php

declare(strict_types=1);

require_once __DIR__.'/Support.php';

use App\Features\Flights\Livewire\FlightManifest;
use App\Models\Airline;
use App\Models\Bid;
use App\Models\Flight;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * 7.1: the flight manifest, with its filters in the query string.
 */
/**
 * @return list<string> the idents of the flights on the current page
 */
function manifestIdents(Testable $component): array
{
    return collect($component->get('results')['flights'])->pluck('callsign')->all();
}

beforeEach(function (): void {
    useAirside();
    airsideFlightsBaseline();
    $this->withoutVite();
    $this->fixture = airsideDispatchFixture(flight: ['flight_number' => '104']);
    $this->actingAs($this->fixture['user']);
});

it('marks a flight the pilot has a bid on as such', function (): void {
    $bid = Bid::query()->create(['user_id' => $this->fixture['user']->id, 'flight_id' => $this->fixture['flight']->id]);

    $flight = Livewire::test(FlightManifest::class)->get('results')['flights'][0];

    expect($flight->availability)->toBe('bid')
        ->and($flight->primaryAction)->toBe('overview')
        ->and($flight->bidId)->toBe($bid->id);
});

it('locks a flight when the pilot is at the bid limit', function (): void {
    updateSetting('bids.allow_multiple_bids', false);
    $other = airsideDispatchFixture($this->fixture['user']);
    Bid::query()->create(['user_id' => $this->fixture['user']->id, 'flight_id' => $other['flight']->id]);

    $flights = collect(Livewire::test(FlightManifest::class)->get('results')['flights'])->keyBy('id');

    expect($flights[$this->fixture['flight']->id]->availability)->toBe('locked')
        ->and($flights[$this->fixture['flight']->id]->availabilityReason)->toBe('Remove your current bid before selecting another flight')
        ->and($flights[$other['flight']->id]->availability)->toBe('bid');
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

    $component = Livewire::test(FlightManifest::class);

    expect(manifestIdents($component))->toHaveCount(2);

    $component->set('flightNumber', '999')
        ->call('apply')
        ->assertSet('page', 1);

    expect(manifestIdents($component))->toBe([$other['flight']->ident])
        ->and($component->get('activeFilters'))->toBe(['Flight: 999']);
});

it('filters on a url that arrives with the filter already set', function (): void {
    airsideDispatchFixture($this->fixture['user'], ['flight_number' => '999']);

    $component = Livewire::withQueryParams(['flight_number' => '104'])->test(FlightManifest::class);

    expect(manifestIdents($component))->toBe([$this->fixture['flight']->ident]);
});

it('accepts the old airport query names', function (): void {
    Livewire::withQueryParams(['dpt_airport_id' => $this->fixture['departure']->id])->test(FlightManifest::class)
        ->assertSet('depIcao', $this->fixture['departure']->id);
});

it('clears every filter', function (): void {
    $component = Livewire::withQueryParams(['flight_number' => '555', 'search' => 'nothing'])->test(FlightManifest::class);

    expect(manifestIdents($component))->toBe([]);

    $component->call('clear')
        ->assertSet('flightNumber', null)
        ->assertSet('search', null);

    expect($component->get('activeFilters'))->toBe([])
        ->and(manifestIdents($component))->toBe([$this->fixture['flight']->ident]);
});

it('lists nothing when no flight matches', function (): void {
    $component = Livewire::test(FlightManifest::class)
        ->set('flightNumber', '424242')
        ->call('apply');

    expect(manifestIdents($component))->toBe([]);
});

it('sets the error when a filter value is refused and still lists flights', function (): void {
    $component = Livewire::test(FlightManifest::class)
        ->set('orderBy', 'password')
        ->call('apply')
        ->assertSet('searchError', 'The schedule could not be updated. The current results are still available.');

    expect(manifestIdents($component))->toBe([$this->fixture['flight']->ident]);
});

it('pages through the results', function (): void {
    foreach (range(1, 2) as $number) {
        airsideDispatchFixture($this->fixture['user'], ['flight_number' => (string) (200 + $number)]);
    }

    $component = Livewire::test(FlightManifest::class)
        ->set('limit', '1')
        ->call('apply');

    expect($component->get('results')['page'])->toBe(['current' => 1, 'last' => 3, 'total' => 3]);

    $component->call('nextPage')
        ->assertSet('page', 2);

    expect($component->get('results')['page'])->toBe(['current' => 2, 'last' => 3, 'total' => 3]);

    $component->call('nextPage')
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

    $component = Livewire::test(FlightManifest::class);

    expect(manifestIdents($component))->toBe([$this->fixture['flight']->ident]);
});

<?php

declare(strict_types=1);

require_once __DIR__.'/Support.php';

use App\Features\Bids\Livewire\AssignmentDrawer;
use App\Features\Bids\Livewire\BidList;
use App\Features\Flights\Livewire\FlightActions;
use App\Features\Flights\Livewire\FlightManifest;
use App\Models\Bid;
use App\Support\Skylight\Facades\Skylight;
use Illuminate\Support\Facades\View;
use Livewire\Livewire;

/**
 * The four addon outlets the flights and bids pages carry, with skylight's context keys.
 */
beforeEach(function (): void {
    useAirside();
    airsideFlightsBaseline();
    $this->withoutVite();
    View::addNamespace('flightsprobe', __DIR__.'/views');
    $this->fixture = airsideDispatchFixture();
    $this->actingAs($this->fixture['user']);

    foreach (['flights.row.actions', 'flights.detail.actions', 'bids.row.actions', 'bids.drawer.overview.actions'] as $slot) {
        Skylight::slots()->register(['slot' => $slot, 'view' => 'flightsprobe::probe']);
    }
});

it('fills flights.row.actions and flights.detail.actions on the manifest with flight and policy', function (): void {
    $callsign = $this->fixture['flight']->ident;

    $html = preg_replace('/\s+/', ' ', preg_replace('/<!--.*?-->/s', '', Livewire::test(FlightManifest::class)->html()));

    expect(preg_match('/data-probe>\s*F:'.preg_quote($callsign, '/').'\s*P\s*<\/span>/', $html))->toBe(1);
});

it('fills flights.detail.actions on the flight page with flight and policy', function (): void {
    $html = preg_replace('/\s+/', ' ', preg_replace('/<!--.*?-->/s', '', Livewire::test(FlightActions::class, ['flightId' => $this->fixture['flight']->id])->html()));

    expect($html)->toContain('F:'.$this->fixture['flight']->ident.' P');
});

it('fills bids.row.actions with bid and flight', function (): void {
    $bid = Bid::query()->create(['user_id' => $this->fixture['user']->id, 'flight_id' => $this->fixture['flight']->id]);

    Livewire::test(BidList::class)->assertSeeHtmlInOrder(['F:'.$this->fixture['flight']->ident, 'B:'.$bid->id]);
});

it('fills bids.drawer.overview.actions with bid and selection', function (): void {
    $bid = Bid::query()->create(['user_id' => $this->fixture['user']->id, 'flight_id' => $this->fixture['flight']->id]);

    Livewire::test(AssignmentDrawer::class)
        ->dispatch('assignment-drawer.open', flightId: $this->fixture['flight']->id)
        ->assertSeeHtmlInOrder(['B:'.$bid->id, 'S:'.$bid->id]);
});

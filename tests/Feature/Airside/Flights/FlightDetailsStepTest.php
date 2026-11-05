<?php

declare(strict_types=1);

require_once __DIR__.'/Support.php';

use App\Features\Flights\Livewire\FlightOverviewDrawer;
use App\Models\Bid;
use Livewire\Livewire;

/**
 * The flight overview drawer, opened from a manifest card, and its hand-over to the bid drawer.
 */
beforeEach(function (): void {
    useAirside();
    airsideFlightsBaseline();
    $this->withoutVite();
    $this->fixture = airsideDispatchFixture();
    $this->actingAs($this->fixture['user']);
});

it('opens on details and hands the flight to the bid drawer on startBid', function (): void {
    $flightId = $this->fixture['flight']->id;

    Livewire::test(FlightOverviewDrawer::class)
        ->dispatch('flight-overview.open', flightId: $flightId)
        ->assertSet('state', 'details')
        ->call('startBid')
        ->assertSet('open', false)
        ->assertDispatched('flight-overview.closed', flightId: $flightId)
        ->assertDispatched('assignment-drawer.open', flightId: $flightId);
});

it('hands a flight that is already on a bid to the bid drawer', function (): void {
    Bid::query()->create(['user_id' => $this->fixture['user']->id, 'flight_id' => $this->fixture['flight']->id]);

    Livewire::test(FlightOverviewDrawer::class)
        ->dispatch('flight-overview.open', flightId: $this->fixture['flight']->id)
        ->assertSet('state', 'details')
        ->call('startBid')
        ->assertDispatched('assignment-drawer.open', flightId: $this->fixture['flight']->id);
});

it('keeps the drawer open when the flight cannot be bid', function (): void {
    updateSetting('bids.allow_multiple_bids', false);
    $other = airsideDispatchFixture($this->fixture['user']);
    Bid::query()->create(['user_id' => $this->fixture['user']->id, 'flight_id' => $other['flight']->id]);

    Livewire::test(FlightOverviewDrawer::class)
        ->dispatch('flight-overview.open', flightId: $this->fixture['flight']->id)
        ->call('startBid')
        ->assertSet('open', true)
        ->assertSet('state', 'details')
        ->assertNotDispatched('assignment-drawer.open');
});

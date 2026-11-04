<?php

declare(strict_types=1);

require_once __DIR__.'/Support.php';

use App\Features\Bids\Livewire\AssignmentDrawer;
use App\Models\Bid;
use Livewire\Livewire;

/**
 * The assignment drawer's opt-in details step, opened from a manifest card.
 */
beforeEach(function (): void {
    useAirside();
    airsideFlightsBaseline();
    $this->withoutVite();
    $this->fixture = airsideDispatchFixture();
    $this->actingAs($this->fixture['user']);
});

it('opens on details when asked and moves to selecting on startBid', function (): void {
    Livewire::test(AssignmentDrawer::class)
        ->dispatch('assignment-drawer.open', flightId: $this->fixture['flight']->id, details: true)
        ->assertSet('state', 'details')
        ->call('startBid')
        ->assertSet('state', 'selecting');
});

it('opens on the overview when a bid exists, even when asked for details', function (): void {
    Bid::query()->create(['user_id' => $this->fixture['user']->id, 'flight_id' => $this->fixture['flight']->id]);

    Livewire::test(AssignmentDrawer::class)
        ->dispatch('assignment-drawer.open', flightId: $this->fixture['flight']->id, details: true)
        ->assertSet('state', 'overview');
});

it('stays on details when the flight cannot be bid', function (): void {
    updateSetting('bids.allow_multiple_bids', false);
    $other = airsideDispatchFixture($this->fixture['user']);
    Bid::query()->create(['user_id' => $this->fixture['user']->id, 'flight_id' => $other['flight']->id]);

    Livewire::test(AssignmentDrawer::class)
        ->dispatch('assignment-drawer.open', flightId: $this->fixture['flight']->id, details: true)
        ->assertSet('state', 'details')
        ->call('startBid')
        ->assertSet('state', 'details');
});

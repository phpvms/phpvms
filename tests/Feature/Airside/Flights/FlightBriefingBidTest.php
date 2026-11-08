<?php

declare(strict_types=1);

require_once __DIR__.'/Support.php';

use App\Enums\AircraftStatus;
use App\Features\Flights\Livewire\FlightBriefing;
use App\Models\Aircraft;
use App\Models\Bid;
use Livewire\Livewire;

/**
 * Placing a bid from the briefing's "Before you fly" rail goes through PlaceBid,
 * as the assignment drawer does.
 */
beforeEach(function (): void {
    useAirside();
    airsideFlightsBaseline();
    $this->withoutVite();
    $this->fixture = airsideDispatchFixture();
    $this->actingAs($this->fixture['user']);
});

it('places the bid with the chosen aircraft', function (): void {
    updateSetting('bids.block_aircraft', true);

    $component = Livewire::test(FlightBriefing::class, ['flightId' => $this->fixture['flight']->id])
        ->call('selectSubfleet', $this->fixture['subfleet']->id)
        ->call('selectAircraft', $this->fixture['aircraft']->id)
        ->call('placeBid')
        ->assertSet('failureMessage', null);

    expect(Bid::query()->where('flight_id', $this->fixture['flight']->id)->value('aircraft_id'))->toBe($this->fixture['aircraft']->id)
        ->and($component->get('detail')['flight']->summary->primaryAction)->toBe('overview');
});

it('refuses to bid without an aircraft when one is required', function (): void {
    updateSetting('bids.block_aircraft', true);

    Livewire::test(FlightBriefing::class, ['flightId' => $this->fixture['flight']->id])
        ->call('placeBid')
        ->assertSet('failureType', 'validation');

    expect(Bid::query()->count())->toBe(0);
});

it('refuses an aircraft that is no longer eligible and clears the choice', function (): void {
    updateSetting('bids.block_aircraft', true);

    $component = Livewire::test(FlightBriefing::class, ['flightId' => $this->fixture['flight']->id])
        ->call('selectSubfleet', $this->fixture['subfleet']->id)
        ->call('selectAircraft', $this->fixture['aircraft']->id);

    Aircraft::query()->whereKey($this->fixture['aircraft']->id)->update(['status' => AircraftStatus::STORED]);

    $component->call('placeBid')
        ->assertSet('failureType', 'stale-aircraft')
        ->assertSet('aircraftId', null);

    expect(Bid::query()->count())->toBe(0);
});

it('does not place a second bid once the pilot has one', function (): void {
    Bid::query()->create(['user_id' => $this->fixture['user']->id, 'flight_id' => $this->fixture['flight']->id]);

    Livewire::test(FlightBriefing::class, ['flightId' => $this->fixture['flight']->id])->call('placeBid');

    expect(Bid::query()->count())->toBe(1);
});

it('cannot act on a flight the pilot cannot see', function (): void {
    $component = Livewire::test(FlightBriefing::class, ['flightId' => $this->fixture['flight']->id]);

    $this->fixture['flight']->update(['visible' => false]);

    $component->call('placeBid')->assertNotFound();

    expect(Bid::query()->count())->toBe(0);
});

it('links Generate OFP by the bid when there is one, and by the flight when there is not', function (): void {
    $component = Livewire::test(FlightBriefing::class, ['flightId' => $this->fixture['flight']->id]);

    expect($component->get('ofpPlanningUrl'))->toBe(route('frontend.ofp.planning').'?flight_id='.$this->fixture['flight']->id);

    $bid = Bid::query()->create(['user_id' => $this->fixture['user']->id, 'flight_id' => $this->fixture['flight']->id, 'aircraft_id' => $this->fixture['aircraft']->id]);

    $component->dispatch('assignment-drawer.closed', flightId: $this->fixture['flight']->id, changed: true);

    expect($component->get('ofpPlanningUrl'))->toBe(route('frontend.ofp.planning', ['bid_id' => $bid->id]));
});

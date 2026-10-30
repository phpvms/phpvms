<?php

declare(strict_types=1);

require_once __DIR__.'/Support.php';

use App\Features\Ofp\Livewire\AircraftSelection;
use App\Models\Aircraft;
use App\Models\Bid;
use App\Models\Subfleet;
use App\Models\User;
use App\Services\BidService;
use Livewire\Livewire;

/**
 * 7.6: the aircraft selection on the planning page.
 */
beforeEach(function (): void {
    $this->fixture = airsideOfpFixture();
    $this->actingAs($this->fixture['user']);
});

it('lists the configured subfleets and loads aircraft for the chosen one', function (): void {
    $empty = Subfleet::factory()->create(['airline_id' => $this->fixture['airline']->id]);
    $this->fixture['flight']->subfleets()->attach($empty);

    Livewire::test(AircraftSelection::class, ['flightId' => $this->fixture['flight']->id])
        ->assertSee('Aircraft Assignment')
        ->assertSee($this->fixture['subfleet']->name)
        ->assertSee('1 available')
        ->assertSee('None available')
        ->assertDontSee($this->fixture['aircraft']->registration)
        ->call('selectSubfleet', $this->fixture['subfleet']->id)
        ->assertSet('subfleetId', $this->fixture['subfleet']->id)
        ->assertSee($this->fixture['aircraft']->registration)
        ->call('selectSubfleet', $empty->id)
        ->assertSet('subfleetId', null);
});

it('goes to planning with the aircraft when there is no bid', function (): void {
    Livewire::test(AircraftSelection::class, ['flightId' => $this->fixture['flight']->id])
        ->call('selectSubfleet', $this->fixture['subfleet']->id)
        ->call('chooseAircraft', $this->fixture['aircraft']->id)
        ->assertRedirect(route('frontend.ofp.planning', [
            'flight_id'   => $this->fixture['flight']->id,
            'aircraft_id' => $this->fixture['aircraft']->id,
        ]));
});

it('writes the aircraft to the bid and revisits planning with the bid', function (): void {
    $bid = app(BidService::class)->addBid($this->fixture['flight'], $this->fixture['user']);

    Livewire::test(AircraftSelection::class, ['flightId' => $this->fixture['flight']->id, 'bidId' => $bid->id])
        ->call('selectSubfleet', $this->fixture['subfleet']->id)
        ->call('chooseAircraft', $this->fixture['aircraft']->id)
        ->assertRedirect(route('frontend.ofp.planning', ['bid_id' => $bid->id]));

    expect($bid->refresh()->aircraft_id)->toBe($this->fixture['aircraft']->id);
});

it('refuses an aircraft that is not eligible', function (): void {
    $elsewhere = Aircraft::factory()->create(['subfleet_id' => Subfleet::factory()->create()->id]);

    Livewire::test(AircraftSelection::class, ['flightId' => $this->fixture['flight']->id])
        ->call('chooseAircraft', $elsewhere->id)
        ->assertNoRedirect()
        ->assertSet('failure', 'This aircraft is no longer eligible. Refresh the aircraft list.')
        ->assertSee('This aircraft is no longer eligible.');
});

it('cannot assign an aircraft to a bid that belongs to another pilot', function (): void {
    $other = User::factory()->create(['airline_id' => $this->fixture['airline']->id]);
    $bid = app(BidService::class)->addBid($this->fixture['flight'], $other);

    Livewire::test(AircraftSelection::class, ['flightId' => $this->fixture['flight']->id, 'bidId' => $bid->id])
        ->call('chooseAircraft', $this->fixture['aircraft']->id)
        ->assertNotFound();

    expect(Bid::query()->find($bid->id)->aircraft_id)->toBeNull();
});

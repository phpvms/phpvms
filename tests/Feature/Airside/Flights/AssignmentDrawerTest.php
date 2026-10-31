<?php

declare(strict_types=1);

require_once __DIR__.'/Support.php';

use App\Enums\AircraftStatus;
use App\Features\Bids\Livewire\AssignmentDrawer;
use App\Features\Hooks\Facades\Hooks;
use App\Features\Hooks\Points\PlacingBid;
use App\Models\Aircraft;
use App\Models\Bid;
use App\Models\Flight;
use App\Models\Subfleet;
use App\Models\User;
use Livewire\Livewire;

/**
 * 7.2: the assignment drawer: dispatch load, subfleet then aircraft, every error
 * the JSON endpoint returns, the overview and the OFP jump.
 */
beforeEach(function (): void {
    useAirside();
    airsideFlightsBaseline();
    $this->withoutVite();
    $this->fixture = airsideDispatchFixture();
    $this->actingAs($this->fixture['user']);
});

function openDrawer(string $flightId)
{
    return Livewire::test(AssignmentDrawer::class)->dispatch('assignment-drawer.open', flightId: $flightId);
}

it('opens on the selection step with the flight, the policy note and the subfleets', function (): void {
    $subfleet = $this->fixture['subfleet'];

    openDrawer($this->fixture['flight']->id)
        ->assertSet('open', true)
        ->assertSet('state', 'selecting')
        ->assertSee($this->fixture['flight']->ident)
        ->assertSee($subfleet->name);
});

it('says the aircraft is reserved when the server requires one', function (): void {
    updateSetting('bids.block_aircraft', true);

    openDrawer($this->fixture['flight']->id)
        ->assertSee('The selected aircraft is reserved exclusively after server confirmation.');
});

it('loads the eligible aircraft for the chosen subfleet and shows the choice', function (): void {
    $component = openDrawer($this->fixture['flight']->id)
        ->call('selectSubfleet', $this->fixture['subfleet']->id)
        ->assertSet('subfleetId', $this->fixture['subfleet']->id)
        ->assertSee('Selected subfleet')
        ->assertSee('1 eligible aircraft currently available')
        ->assertSee($this->fixture['aircraft']->registration);

    $component->call('selectAircraft', $this->fixture['aircraft']->id)
        ->assertSet('aircraftId', $this->fixture['aircraft']->id)
        ->assertSee('Selected aircraft');
});

it('lists a subfleet with no eligible aircraft as none available', function (): void {
    $empty = Subfleet::factory()->create(['airline_id' => $this->fixture['airline']->id]);
    $this->fixture['flight']->subfleets()->attach($empty);

    openDrawer($this->fixture['flight']->id)
        ->assertSee('None available');
});

it('shows the empty aircraft state for an optional and a required aircraft', function (): void {
    $empty = Subfleet::factory()->create(['airline_id' => $this->fixture['airline']->id]);
    $this->fixture['flight']->subfleets()->attach($empty);

    openDrawer($this->fixture['flight']->id)
        ->call('selectSubfleet', $empty->id)
        ->assertSee('No eligible aircraft')
        ->assertSee('You can still create a flight-only bid.');

    updateSetting('bids.block_aircraft', true);

    openDrawer($this->fixture['flight']->id)
        ->call('selectSubfleet', $empty->id)
        ->assertSee('No active, parked aircraft meets the current server policy for this flight.');
});

it('places a flight-only bid and switches to the overview in place', function (): void {
    openDrawer($this->fixture['flight']->id)
        ->call('submit')
        ->assertSet('state', 'overview')
        ->assertSet('failureMessage', null)
        ->assertSee('Scheduled departure')
        ->assertSee('Reservation')
        ->assertSee('Flight only')
        ->assertSee('Does not expire')
        ->assertSee('Aircraft not selected');

    expect(Bid::query()->where('flight_id', $this->fixture['flight']->id)->count())->toBe(1);
});

it('places a bid with an aircraft and shows it as a preference', function (): void {
    openDrawer($this->fixture['flight']->id)
        ->call('selectSubfleet', $this->fixture['subfleet']->id)
        ->call('selectAircraft', $this->fixture['aircraft']->id)
        ->call('submit')
        ->assertSet('state', 'overview')
        ->assertSee('Preference')
        ->assertSee($this->fixture['aircraft']->registration);

    expect(Bid::query()->first()->aircraft_id)->toBe($this->fixture['aircraft']->id);
});

it('shows an exclusive reservation when the server requires an aircraft', function (): void {
    updateSetting('bids.block_aircraft', true);

    openDrawer($this->fixture['flight']->id)
        ->call('selectSubfleet', $this->fixture['subfleet']->id)
        ->call('selectAircraft', $this->fixture['aircraft']->id)
        ->call('submit')
        ->assertSee('Exclusive');
});

it('opens on the overview when the pilot already has a bid', function (): void {
    Bid::query()->create(['user_id' => $this->fixture['user']->id, 'flight_id' => $this->fixture['flight']->id]);

    openDrawer($this->fixture['flight']->id)
        ->assertSet('state', 'overview')
        ->assertSee('Scheduled arrival')
        ->assertSee('Route')
        ->assertSee('DCT TEST');
});

it('offers View OFP when a briefing exists and Generate OFP when SimBrief is on', function (): void {
    updateSetting('simbrief.api_key', 'key');
    $bid = Bid::query()->create(['user_id' => $this->fixture['user']->id, 'flight_id' => $this->fixture['flight']->id]);

    openDrawer($this->fixture['flight']->id)
        ->assertSee('Generate OFP')
        ->assertSee(route('frontend.ofp.planning', ['bid_id' => $bid->id]), false);
});

it('goes to OFP planning after a first bid when SimBrief is on', function (): void {
    updateSetting('simbrief.api_key', 'key');

    $component = openDrawer($this->fixture['flight']->id)->call('submit');
    $bid = Bid::query()->firstOrFail();

    $component->assertRedirect(route('frontend.ofp.planning', ['bid_id' => $bid->id]));
});

it('refuses a bid without an aircraft when one is required', function (): void {
    updateSetting('bids.block_aircraft', true);

    openDrawer($this->fixture['flight']->id)
        ->call('submit')
        ->assertSet('failureType', 'validation')
        ->assertSet('failureMessage', 'Select an aircraft before placing this bid.')
        ->assertSet('state', 'selecting')
        ->assertSee('Select an aircraft before placing this bid.');

    expect(Bid::query()->count())->toBe(0);
});

it('reports the pilot bid limit', function (): void {
    updateSetting('bids.allow_multiple_bids', false);
    $other = airsideDispatchFixture($this->fixture['user']);
    Bid::query()->create(['user_id' => $this->fixture['user']->id, 'flight_id' => $other['flight']->id]);

    openDrawer($this->fixture['flight']->id)
        ->call('submit')
        ->assertSet('failureType', 'pilot-limit')
        ->assertSet('state', 'selecting')
        ->assertSet('failureMessage', fn ($message): bool => filled($message));
});

it('reports a flight another pilot already holds', function (): void {
    updateSetting('bids.disable_flight_on_bid', true);
    Bid::query()->create(['user_id' => User::factory()->create()->id, 'flight_id' => $this->fixture['flight']->id]);

    openDrawer($this->fixture['flight']->id)
        ->call('submit')
        ->assertSet('failureType', 'flight-conflict')
        ->assertSet('failureMessage', fn ($message): bool => filled($message));
});

it('reports an aircraft conflict, refetches the lists and clears the choice', function (): void {
    updateSetting('bids.block_aircraft', true);

    $component = openDrawer($this->fixture['flight']->id)
        ->call('selectSubfleet', $this->fixture['subfleet']->id)
        ->call('selectAircraft', $this->fixture['aircraft']->id);

    // Another pilot takes the aircraft while the drawer is open.
    $otherFlight = Flight::factory()->hasAttached($this->fixture['subfleet'])->create([
        'airline_id'     => $this->fixture['airline']->id,
        'dpt_airport_id' => $this->fixture['departure']->id,
        'arr_airport_id' => $this->fixture['arrival']->id,
    ]);
    Bid::query()->create([
        'user_id'     => User::factory()->create()->id,
        'flight_id'   => $otherFlight->id,
        'aircraft_id' => $this->fixture['aircraft']->id,
    ]);

    $component->call('submit')
        ->assertSet('failureType', 'aircraft-conflict')
        ->assertSet('failureMessage', 'A bid already exists for this aircraft')
        ->assertSet('aircraftId', null)
        ->assertSet('subfleetId', null)
        ->assertSet('editingSubfleet', true)
        ->assertSee('A bid already exists for this aircraft')
        ->assertSee('None available');

    expect(Bid::query()->where('user_id', $this->fixture['user']->id)->count())->toBe(0);
});

it('reports a stale aircraft that no longer exists', function (): void {
    $component = openDrawer($this->fixture['flight']->id)
        ->call('selectSubfleet', $this->fixture['subfleet']->id)
        ->set('aircraftId', 987654)
        ->call('submit')
        ->assertSet('failureType', 'stale-aircraft')
        ->assertSet('failureMessage', 'This aircraft is no longer available. Refresh the aircraft list.')
        ->assertSet('aircraftId', null)
        ->assertSet('subfleetId', null);

    expect($component->get('state'))->toBe('selecting');
});

it('reports a stale aircraft that is no longer eligible and clears it', function (): void {
    $component = openDrawer($this->fixture['flight']->id)
        ->call('selectSubfleet', $this->fixture['subfleet']->id)
        ->call('selectAircraft', $this->fixture['aircraft']->id);

    Aircraft::query()->whereKey($this->fixture['aircraft']->id)->update(['status' => AircraftStatus::STORED]);

    $component->call('submit')
        ->assertSet('failureType', 'stale-aircraft')
        ->assertSet('failureMessage', 'This aircraft is no longer eligible. Refresh the aircraft list.')
        ->assertSet('aircraftId', null)
        ->assertSet('subfleetId', null)
        ->assertSee('None available');
});

it('reports a bid an addon gate denies', function (): void {
    Hooks::fake()->deny(PlacingBid::class, 'Complete your checkride first');

    openDrawer($this->fixture['flight']->id)
        ->call('submit')
        ->assertSet('failureType', 'hook-denied')
        ->assertSet('failureMessage', 'Complete your checkride first')
        ->assertSee('Complete your checkride first')
        ->assertSet('state', 'selecting');

    expect(Bid::query()->count())->toBe(0);
});

it('shows the load error and loads again on Try again', function (): void {
    $stranger = Flight::factory()->create(['airline_id' => $this->fixture['airline']->id, 'visible' => false]);

    openDrawer($stranger->id)
        ->assertSet('state', 'error')
        ->assertSee('Flight data unavailable')
        ->assertSee('The flight could not be loaded. Check your connection and try again.')
        ->assertSee('Try again')
        ->call('load')
        ->assertSet('state', 'error');
});

it('reports the closed event with whether a bid changed', function (): void {
    $flightId = $this->fixture['flight']->id;

    openDrawer($flightId)
        ->call('close')
        ->assertDispatched('assignment-drawer.closed', flightId: $flightId, changed: false)
        ->assertSet('open', false)
        ->assertSet('flightId', null);

    openDrawer($flightId)
        ->call('submit')
        ->call('close')
        ->assertDispatched('assignment-drawer.closed', flightId: $flightId, changed: true);
});

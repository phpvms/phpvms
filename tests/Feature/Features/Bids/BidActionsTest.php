<?php

declare(strict_types=1);

use App\Features\Bids\Actions\LoadDispatch;
use App\Features\Bids\Actions\PlaceBid;
use App\Features\Bids\Actions\RemoveBid;
use App\Features\Hooks\Exceptions\HookDenied;
use App\Features\Hooks\Facades\Hooks;
use App\Features\Hooks\Points\PlacingBid;
use App\Features\Tour\Models\UserTour;
use App\Models\Aircraft;
use App\Models\Airport;
use App\Models\Bid;
use App\Models\Flight;
use App\Models\Subfleet;
use App\Models\User;

beforeEach(function (): void {
    tourSettingsBaseline();
});

/**
 * @return array{user: User, flight: Flight, aircraft: Aircraft}
 */
function bidActionFixture(): array
{
    $departure = Airport::factory()->create();
    $user = User::factory()->create(['curr_airport_id' => $departure->id]);
    $subfleet = Subfleet::factory()->create(['airline_id' => $user->airline_id]);
    $aircraft = Aircraft::factory()->create(['subfleet_id' => $subfleet->id, 'airport_id' => $departure->id]);
    $flight = Flight::factory()->hasAttached($subfleet)->create([
        'airline_id'     => $user->airline_id,
        'dpt_airport_id' => $departure->id,
    ]);

    return ['user' => $user, 'flight' => $flight, 'aircraft' => $aircraft];
}

it('places a bid and runs the PlacingBid gate', function (): void {
    $hooks = Hooks::fake();
    ['user' => $user, 'flight' => $flight, 'aircraft' => $aircraft] = bidActionFixture();

    $bid = app(PlaceBid::class)->handle($flight, $user, $aircraft);

    expect($bid->flight_id)->toBe($flight->id)
        ->and($bid->aircraft_id)->toBe($aircraft->id);
    $hooks->assertDispatched(PlacingBid::class, fn (PlacingBid $gate): bool => $gate->flight->is($flight) && !$gate->startsTour);
});

it('creates no bid when a gate denies it', function (): void {
    Hooks::fake()->deny(PlacingBid::class, 'Complete your checkride first');
    ['user' => $user, 'flight' => $flight] = bidActionFixture();

    expect(fn () => app(PlaceBid::class)->handle($flight, $user))
        ->toThrow(HookDenied::class, 'Complete your checkride first');
    expect(Bid::query()->count())->toBe(0);
});

it('skips the gate when the pilot already holds a bid for the flight', function (): void {
    ['user' => $user, 'flight' => $flight, 'aircraft' => $aircraft] = bidActionFixture();
    $existing = app(PlaceBid::class)->handle($flight, $user);
    Hooks::fake()->deny(PlacingBid::class, 'One bid at a time');

    $bid = app(PlaceBid::class)->handle($flight, $user, $aircraft);

    expect($bid->id)->toBe($existing->id)
        ->and($bid->aircraft_id)->toBe($aircraft->id);
});

it('gates a tour start once, before any leg bid is written', function (): void {
    $hooks = Hooks::fake()->deny(PlacingBid::class, 'No tours today');
    ['user' => $user, 'flights' => $flights, 'aircraft' => $aircraft] = makeTour(3);

    expect(fn () => app(PlaceBid::class)->handle($flights[0], $user, $aircraft))->toThrow(HookDenied::class);
    expect(Bid::query()->count())->toBe(0)
        ->and(UserTour::query()->count())->toBe(0);
    $hooks->assertDispatched(PlacingBid::class, fn (PlacingBid $gate): bool => $gate->startsTour);
});

it('returns a 422 hook-denied response from the web bid endpoint', function (): void {
    Hooks::fake()->deny(PlacingBid::class, 'Complete your checkride first');
    ['user' => $user, 'flight' => $flight] = bidActionFixture();

    $this->actingAs($user)
        ->postJson(route('frontend.flights.bid.store', $flight->id), ['aircraftId' => null])
        ->assertStatus(422)
        ->assertExactJson(['type' => 'hook-denied', 'message' => 'Complete your checkride first']);
    expect(Bid::query()->count())->toBe(0);
});

it('returns a 422 hook-denied problem from the API bid endpoint', function (): void {
    Hooks::fake()->deny(PlacingBid::class, 'Complete your checkride first');
    ['user' => $user, 'flight' => $flight] = bidActionFixture();
    apiAs($user);

    $this->put('/api/user/bids', ['flight_id' => $flight->id])
        ->assertStatus(422)
        ->assertJsonPath('details', 'Complete your checkride first')
        ->assertJsonPath('hook', 'bid.placing');
    expect(Bid::query()->count())->toBe(0);
});

it('loads the dispatch payload for a flight, with the pilot bid once placed', function (): void {
    ['user' => $user, 'flight' => $flight, 'aircraft' => $aircraft] = bidActionFixture();

    $before = app(LoadDispatch::class)->handle($user, $flight->id);
    app(PlaceBid::class)->handle($flight, $user, $aircraft);
    $after = app(LoadDispatch::class)->handle($user, $flight->id);

    expect($before['selection'])->toBeNull()
        ->and($before['subfleets'])->toHaveCount(1)
        ->and($before['subfleets'][0]->eligibleAircraftCount)->toBe(1)
        ->and($after['selection']?->bid->id)->not->toBeNull();
});

it('removes a plain bid', function (): void {
    ['user' => $user, 'flight' => $flight] = bidActionFixture();
    app(PlaceBid::class)->handle($flight, $user);

    app(RemoveBid::class)->handle($user, $flight->id);

    expect(Bid::query()->count())->toBe(0);
});

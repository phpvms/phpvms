<?php

declare(strict_types=1);

require_once __DIR__.'/../Support.php';

use App\Enums\BundleType;
use App\Features\Bids\Actions\RemoveBid;
use App\Features\Tour\Enums\TourStatus;
use App\Features\Tour\Livewire\TourList;
use App\Features\Tour\Models\UserTour;
use App\Features\Tour\TourService;
use App\Models\Bid;
use App\Models\FlightBundle;
use App\Models\User;
use App\Services\BidService;
use Livewire\Livewire;

/**
 * 7.9: tours index and show under airside.
 */
beforeEach(function (): void {
    useAirside();
    tourSettingsBaseline();
    $this->withoutVite();
});

it('lists tours as cards with the bid action and the timeline', function (): void {
    ['bundle' => $bundle, 'flights' => $flights, 'user' => $user] = makeTour(3);
    $bundle->update(['description' => 'Island hop around the bay', 'start_date' => '2026-01-05', 'end_date' => '2026-03-09']);
    FlightBundle::factory()->create(['type' => BundleType::Tour, 'enabled' => false]);

    $this->actingAs($user)
        ->get('/tours')
        ->assertOk()
        ->assertSee('Multi-leg journeys flown in order.')
        ->assertSee('All tours')
        ->assertSee('My tours')
        ->assertSee($bundle->name)
        ->assertSee('Island hop around the bay')
        ->assertSee('Jan 5, 2026 – Mar 9, 2026')
        ->assertSee($flights[0]->airline->code.$flights[0]->flight_number)
        ->assertSee('Bid this tour')
        ->assertDontSee('Cancel tour')
        ->assertSee('1 tour');
});

it('shows the empty state when no tour is published', function (): void {
    $this->actingAs(User::factory()->create())
        ->get('/tours')
        ->assertOk()
        ->assertSee('No tours yet')
        ->assertSee('When your airline publishes a tour, it will appear here ready to bid.');
});

it('marks the run in progress, the flown leg and the next one', function (): void {
    ['flights' => $flights, 'user' => $user, 'aircraft' => $aircraft] = makeTour(3);
    app(BidService::class)->addBid($flights[0], $user, $aircraft);
    fileTourLeg($user, $flights[0], $aircraft);

    $this->actingAs($user)
        ->get('/tours')
        ->assertOk()
        ->assertSee('In progress · 1 of 3 legs')
        ->assertSee('Flown')
        ->assertSee('Up next')
        ->assertSee('Continue tour')
        ->assertSee('Cancel tour');
});

it('windows a long tour to six legs and offers all of them', function (): void {
    ['flights' => $flights, 'user' => $user] = makeTour(10);

    $html = $this->actingAs($user)->get('/tours')->assertOk()->assertSee('Show all 10 legs')->getContent();

    expect(substr_count($html, 'data-leg-window="in"'))->toBe(6)
        ->and(substr_count($html, 'data-leg-window="out"'))->toBe(4);
});

it('does not window a tour of eight legs or fewer', function (): void {
    ['user' => $user] = makeTour(8);

    $html = $this->actingAs($user)->get('/tours')->assertOk()->assertDontSee('Show all')->getContent();

    expect(substr_count($html, 'data-leg-window="out"'))->toBe(0);
});

it('asks the assignment drawer to open on the active leg', function (): void {
    ['flights' => $flights, 'user' => $user] = makeTour(3);

    $this->actingAs($user)
        ->get('/tours')
        ->assertSee("assignment-drawer.open', { flightId: '{$flights[0]->id}' }", false);
});

it('shows one tour read-only', function (): void {
    ['bundle' => $bundle, 'flights' => $flights, 'user' => $user] = makeTour(3);

    $this->actingAs($user)
        ->get('/tours/'.$bundle->id)
        ->assertOk()
        ->assertSee($bundle->name)
        ->assertSee('Flights · Tours')
        ->assertSee('All tours')
        ->assertSee('Legs')
        ->assertSee($flights[2]->airline->code.$flights[2]->flight_number)
        ->assertSee('Back to my bids')
        ->assertDontSee('Bid this tour')
        ->assertDontSee('Cancel tour');
});

it('keeps the cancel tour confirmation inline with the skylight copy', function (): void {
    ['flights' => $flights, 'user' => $user, 'aircraft' => $aircraft] = makeTour(3);
    app(BidService::class)->addBid($flights[0], $user, $aircraft);

    $this->actingAs($user)
        ->get('/tours')
        ->assertSee('Cancel this tour?')
        ->assertSee('Yes, cancel it')
        ->assertSee('Keep flying');
});

it('cancels the tour through the remove-bid action', function (): void {
    ['bundle' => $bundle, 'flights' => $flights, 'user' => $user, 'aircraft' => $aircraft] = makeTour(3);
    app(BidService::class)->addBid($flights[0], $user, $aircraft);

    expect(UserTour::query()->where('user_id', $user->id)->value('status'))->toBe(TourStatus::InProgress);

    Livewire::actingAs($user)
        ->test(TourList::class)
        ->assertSee('In progress')
        ->call('cancelTour', $bundle->id)
        ->assertSet('cancelErrors', [])
        ->assertSee('Cancelled')
        ->assertDontSee('In progress');

    expect(UserTour::query()->where('user_id', $user->id)->value('status'))->toBe(TourStatus::Cancelled)
        ->and(Bid::query()->where('user_id', $user->id)->count())->toBe(0);
});

it('only cancels a tour that is in progress', function (): void {
    ['bundle' => $bundle, 'user' => $user] = makeTour(3);

    app()->instance(RemoveBid::class, new class()
    {
        public function handle(): never
        {
            throw new LogicException('A tour that is not in progress must not be cancelled.');
        }
    });

    Livewire::actingAs($user)
        ->test(TourList::class)
        ->call('cancelTour', $bundle->id)
        ->assertSet('cancelErrors', [])
        ->assertSee('Bid this tour');
});

it('shows the skylight error on the card when the cancel fails', function (): void {
    ['bundle' => $bundle, 'flights' => $flights, 'user' => $user, 'aircraft' => $aircraft] = makeTour(3);
    app(BidService::class)->addBid($flights[0], $user, $aircraft);

    app()->instance(RemoveBid::class, new class()
    {
        public function handle(): never
        {
            throw new RuntimeException('boom');
        }
    });

    Livewire::actingAs($user)
        ->test(TourList::class)
        ->call('cancelTour', $bundle->id)
        ->assertSet('cancelErrors', [$bundle->id => 'The tour could not be cancelled. Try again.'])
        ->assertSee('The tour could not be cancelled. Try again.')
        ->assertSee('In progress');
});

it('reloads the tours when the assignment drawer closes', function (): void {
    ['flights' => $flights, 'user' => $user, 'aircraft' => $aircraft] = makeTour(3);

    $component = Livewire::actingAs($user)
        ->test(TourList::class)
        ->assertSee('Bid this tour')
        ->assertDontSee('In progress');

    app(BidService::class)->addBid($flights[0], $user, $aircraft);

    $component
        ->dispatch('assignment-drawer.closed', flightId: $flights[0]->id, changed: true)
        ->assertSee('In progress · 0 of 3 legs')
        ->assertSee('Continue tour');
});

it('does not overwrite a cancellation that landed while the expiry was finishing the run', function (): void {
    ['flights' => $flights, 'user' => $user, 'aircraft' => $aircraft] = makeTour(3);
    app(BidService::class)->addBid($flights[0], $user, $aircraft);

    // The sweeper loaded the run while it was in progress; the pilot cancelled
    // it before the sweeper's transaction started.
    $stale = UserTour::query()->where('user_id', $user->id)->firstOrFail();
    UserTour::query()->whereKey($stale->id)->update(['status' => TourStatus::Cancelled]);

    app(TourService::class)->expire($stale);

    expect(UserTour::query()->whereKey($stale->id)->value('status'))->toBe(TourStatus::Cancelled);
});

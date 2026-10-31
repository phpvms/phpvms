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
    $disabled = FlightBundle::factory()->create(['type' => BundleType::Tour, 'enabled' => false]);

    $this->actingAs($user)
        ->get('/tours')
        ->assertOk()
        ->assertSee($bundle->name)
        ->assertDontSee($disabled->name)
        ->assertSee('Island hop around the bay')
        ->assertSee('Jan 5, 2026 – Mar 9, 2026')
        ->assertSee($flights[0]->airline->code.$flights[0]->flight_number);
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

it('windows a long tour and offers all of its legs', function (): void {
    ['user' => $user] = makeTour(10);

    $html = $this->actingAs($user)->get('/tours')->assertOk()->assertSee('Show all 10 legs')->getContent();

    expect($html)->toContain('data-leg-window="in"')->toContain('data-leg-window="out"');
});

it('does not window a tour of eight legs or fewer', function (): void {
    ['user' => $user] = makeTour(8);

    $html = $this->actingAs($user)->get('/tours')->assertOk()->assertDontSee('Show all')->getContent();

    expect($html)->not->toContain('data-leg-window="out"');
});

it('asks the assignment drawer to open on the active leg', function (): void {
    ['flights' => $flights, 'user' => $user] = makeTour(3);

    $this->actingAs($user)
        ->get('/tours')
        ->assertSee("flightId: '{$flights[0]->id}'", false);
});

it('shows one tour read-only', function (): void {
    ['bundle' => $bundle, 'flights' => $flights, 'user' => $user] = makeTour(3);

    $this->actingAs($user)
        ->get('/tours/'.$bundle->id)
        ->assertOk()
        ->assertSee($bundle->name)
        ->assertSee($flights[2]->airline->code.$flights[2]->flight_number)
        ->assertDontSee('Bid this tour')
        ->assertDontSee('Cancel tour');
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

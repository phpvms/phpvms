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
 * 7.9: the tours list component: run state per tour, cancelling and reloading.
 */
beforeEach(function (): void {
    useAirside();
    tourSettingsBaseline();
    $this->withoutVite();
});

it('lists enabled tours and leaves out disabled ones', function (): void {
    ['bundle' => $bundle, 'user' => $user] = makeTour(3);
    $disabled = FlightBundle::factory()->create(['type' => BundleType::Tour, 'enabled' => false]);

    $tours = Livewire::actingAs($user)->test(TourList::class)->get('tours');

    expect(collect($tours)->pluck('id')->all())->toBe([$bundle->id])
        ->and(collect($tours)->pluck('id')->all())->not->toContain($disabled->id);
});

it('marks the flown leg from the bid and the filed PIREP', function (): void {
    ['flights' => $flights, 'user' => $user, 'aircraft' => $aircraft] = makeTour(3);
    app(BidService::class)->addBid($flights[0], $user, $aircraft);
    fileTourLeg($user, $flights[0], $aircraft);

    $tour = Livewire::actingAs($user)->test(TourList::class)->get('tours')[0];

    expect($tour->status)->toBe(TourStatus::InProgress->value)
        ->and($tour->legsCompleted)->toBe(1)
        ->and(collect($tour->legs)->pluck('flown')->all())->toBe([true, false, false]);
});

it('opens the assignment drawer on the active leg', function (): void {
    ['flights' => $flights, 'user' => $user] = makeTour(3);

    $tour = Livewire::actingAs($user)->test(TourList::class)->get('tours')[0];

    expect($tour->activeLegFlightId)->toBe($flights[0]->id);
});

it('cancels the tour through the remove-bid action', function (): void {
    ['bundle' => $bundle, 'flights' => $flights, 'user' => $user, 'aircraft' => $aircraft] = makeTour(3);
    app(BidService::class)->addBid($flights[0], $user, $aircraft);

    expect(UserTour::query()->where('user_id', $user->id)->value('status'))->toBe(TourStatus::InProgress);

    Livewire::actingAs($user)
        ->test(TourList::class)
        ->call('cancelTour', $bundle->id)
        ->assertSet('cancelErrors', []);

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
        ->assertSet('cancelErrors', []);
});

it('reports an error and keeps the run when the cancel fails', function (): void {
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
        ->assertSet('cancelErrors', [$bundle->id => 'The tour could not be cancelled. Try again.']);

    expect(UserTour::query()->where('user_id', $user->id)->value('status'))->toBe(TourStatus::InProgress);
});

it('reloads the tours when the assignment drawer closes', function (): void {
    ['flights' => $flights, 'user' => $user, 'aircraft' => $aircraft] = makeTour(3);

    $component = Livewire::actingAs($user)->test(TourList::class);

    expect($component->get('tours')[0]->status)->toBeNull();

    app(BidService::class)->addBid($flights[0], $user, $aircraft);

    $component->dispatch('assignment-drawer.closed', flightId: $flights[0]->id, changed: true);

    expect($component->get('tours')[0]->status)->toBe(TourStatus::InProgress->value)
        ->and($component->get('tours')[0]->legsCompleted)->toBe(0);
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

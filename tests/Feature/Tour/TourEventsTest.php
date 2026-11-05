<?php

declare(strict_types=1);

use App\Features\Tour\Enums\TourStatus;
use App\Features\Tour\Events\TourCancelled;
use App\Features\Tour\Events\TourCompleted;
use App\Features\Tour\Events\TourExpired;
use App\Features\Tour\Events\TourLegCompleted;
use App\Features\Tour\Events\TourStarted;
use App\Features\Tour\Models\UserTour;
use App\Features\Tour\TourService;
use App\Models\Flight;
use App\Services\BidService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    tourSettingsBaseline();
    Event::fake([TourStarted::class, TourLegCompleted::class, TourCompleted::class, TourCancelled::class, TourExpired::class]);
});

it('announces a start once, even when the start is submitted twice', function (): void {
    ['user' => $user, 'flights' => $flights, 'aircraft' => $aircraft] = makeTour(3);

    app(BidService::class)->addBid($flights[0], $user, $aircraft);
    app(BidService::class)->addBid($flights[0], $user, $aircraft);

    Event::assertDispatchedTimes(TourStarted::class, 1);
});

it('announces completion only when the final leg is filed', function (): void {
    ['user' => $user, 'flights' => $flights, 'aircraft' => $aircraft] = makeTour(2);
    app(BidService::class)->addBid($flights[0], $user, $aircraft);

    fileTourLeg($user, $flights[0], $aircraft);
    Event::assertNotDispatched(TourCompleted::class);

    fileTourLeg($user, $flights[1], $aircraft);
    Event::assertDispatchedTimes(TourCompleted::class, 1);
});

it('announces a cancellation once and stays silent for a finished tour', function (): void {
    ['user' => $user, 'flights' => $flights, 'aircraft' => $aircraft] = makeTour(3);
    app(BidService::class)->addBid($flights[0], $user, $aircraft);
    $tour = UserTour::query()->where('user_id', $user->id)->firstOrFail();

    app(TourService::class)->cancel($tour);
    app(TourService::class)->cancel($tour->refresh());
    app(TourService::class)->expire($tour->refresh());

    Event::assertDispatchedTimes(TourCancelled::class, 1);
    Event::assertNotDispatched(TourExpired::class);
});

it('ignores an expiry that arrives with a stale copy of a cancelled tour', function (): void {
    ['user' => $user, 'flights' => $flights, 'aircraft' => $aircraft] = makeTour(3);
    app(BidService::class)->addBid($flights[0], $user, $aircraft);
    $id = UserTour::query()->where('user_id', $user->id)->firstOrFail()->id;
    $a = UserTour::query()->findOrFail($id);
    $b = UserTour::query()->findOrFail($id);

    app(TourService::class)->cancel($a);
    app(TourService::class)->expire($b);

    Event::assertDispatchedTimes(TourCancelled::class, 1);
    Event::assertNotDispatched(TourExpired::class);
    expect(UserTour::query()->findOrFail($id)->status)->toBe(TourStatus::Cancelled);
});

it('stays silent when cancelling a completed tour', function (): void {
    ['user' => $user, 'flights' => $flights, 'aircraft' => $aircraft] = makeTour(1);
    app(BidService::class)->addBid($flights[0], $user, $aircraft);
    fileTourLeg($user, $flights[0], $aircraft);
    $tour = UserTour::query()->where('user_id', $user->id)->firstOrFail();

    app(TourService::class)->cancel($tour);

    Event::assertNotDispatched(TourCancelled::class);
});

it('announces expiry as expired, not cancelled', function (): void {
    ['user' => $user, 'flights' => $flights, 'aircraft' => $aircraft] = makeTour(3);
    app(BidService::class)->addBid($flights[0], $user, $aircraft);
    $tour = UserTour::query()->where('user_id', $user->id)->firstOrFail();

    app(TourService::class)->expire($tour);

    Event::assertDispatchedTimes(TourExpired::class, 1);
    Event::assertNotDispatched(TourCancelled::class);
});

it('announces a filed leg with its pirep, number and progress', function (): void {
    ['user' => $user, 'flights' => $flights, 'aircraft' => $aircraft] = makeTour(3);
    app(BidService::class)->addBid($flights[0], $user, $aircraft);

    $pirep = fileTourLeg($user, $flights[0], $aircraft);

    Event::assertDispatchedTimes(TourLegCompleted::class, 1);
    Event::assertDispatched(TourLegCompleted::class, fn (TourLegCompleted $event): bool => $event->legNumber === 1
        && $event->completesTour === false
        && $event->pirep->id === $pirep->id
        && $event->flight?->id === $flights[0]->id
        && $event->tour->legs_completed === 1);
});

it('marks the final leg and announces completion after it', function (): void {
    ['user' => $user, 'flights' => $flights, 'aircraft' => $aircraft] = makeTour(2);
    app(BidService::class)->addBid($flights[0], $user, $aircraft);

    fileTourLeg($user, $flights[0], $aircraft);
    fileTourLeg($user, $flights[1], $aircraft);

    Event::assertDispatched(TourLegCompleted::class, fn (TourLegCompleted $event): bool => $event->completesTour
        && $event->legNumber === 2);
    Event::assertDispatched(TourCompleted::class);
});

it('dispatches the final leg before the completion', function (): void {
    ['user' => $user, 'flights' => $flights, 'aircraft' => $aircraft] = makeTour(2);
    app(BidService::class)->addBid($flights[0], $user, $aircraft);
    fileTourLeg($user, $flights[0], $aircraft);

    // Re-faking unwraps the fake from beforeEach, so these two reach real listeners.
    Event::fake([TourStarted::class]);
    $order = [];
    Event::listen(TourLegCompleted::class, function () use (&$order): void {
        $order[] = TourLegCompleted::class;
    });
    Event::listen(TourCompleted::class, function () use (&$order): void {
        $order[] = TourCompleted::class;
    });

    fileTourLeg($user, $flights[1], $aircraft);

    expect($order)->toBe([TourLegCompleted::class, TourCompleted::class]);
});

it('does not announce a start when the surrounding transaction rolls back', function (): void {
    ['user' => $user, 'flights' => $flights, 'aircraft' => $aircraft] = makeTour(3);

    try {
        DB::transaction(function () use ($user, $flights, $aircraft): void {
            app(BidService::class)->addBid($flights[0], $user, $aircraft);

            throw new RuntimeException('rollback');
        });
    } catch (RuntimeException) {
    }

    Event::assertNotDispatched(TourStarted::class);
});

it('does not announce a cancellation when the surrounding transaction rolls back', function (): void {
    ['user' => $user, 'flights' => $flights, 'aircraft' => $aircraft] = makeTour(3);
    app(BidService::class)->addBid($flights[0], $user, $aircraft);
    $tour = UserTour::query()->where('user_id', $user->id)->firstOrFail();

    try {
        DB::transaction(function () use ($tour): void {
            app(TourService::class)->cancel($tour);

            throw new RuntimeException('rollback');
        });
    } catch (RuntimeException) {
    }

    Event::assertNotDispatched(TourCancelled::class);
});

it('flags completion on the filing that completes the tour, not on the last route leg', function (): void {
    ['user' => $user, 'flights' => $flights, 'aircraft' => $aircraft] = makeTour(3);
    app(BidService::class)->addBid($flights[0], $user, $aircraft);

    foreach ([0, 2, 1] as $index) {
        fileTourLeg($user, $flights[$index], $aircraft);
    }

    Event::assertDispatched(TourLegCompleted::class, fn (TourLegCompleted $event): bool => $event->legNumber === 3
        && $event->completesTour === false);
    Event::assertDispatched(TourLegCompleted::class, fn (TourLegCompleted $event): bool => $event->legNumber === 2
        && $event->completesTour);
});

it('does not announce a leg again when the same pirep advances the tour twice', function (): void {
    ['user' => $user, 'flights' => $flights, 'aircraft' => $aircraft] = makeTour(3);
    app(BidService::class)->addBid($flights[0], $user, $aircraft);

    $pirep = fileTourLeg($user, $flights[0], $aircraft);
    app(TourService::class)->advance($pirep);

    Event::assertDispatchedTimes(TourLegCompleted::class, 1);
});

it('announces nothing for a pirep on a flight outside any live tour', function (): void {
    ['user' => $user, 'aircraft' => $aircraft] = makeTour(3);
    $loose = Flight::factory()->create(['airline_id' => $user->airline_id]);

    fileTourLeg($user, $loose, $aircraft);

    Event::assertNotDispatched(TourLegCompleted::class);
});

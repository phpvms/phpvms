<?php

declare(strict_types=1);

use App\Enums\PirepPhase;
use App\Features\Tour\Enums\TourStatus;
use App\Features\Tour\Models\UserTour;
use App\Models\Bid;
use App\Models\Flight;
use App\Models\Pirep;
use App\Services\BidService;
use App\Services\PirepService;

beforeEach(function (): void {
    tourSettingsBaseline();
});

it('advances one leg, keeps the rest of the run intact', function (): void {
    ['user' => $user, 'flights' => $flights, 'aircraft' => $aircraft] = makeTour(5);
    app(BidService::class)->addBid($flights[0], $user, $aircraft);

    fileTourLeg($user, $flights[0], $aircraft);
    $pirep = fileTourLeg($user, $flights[1], $aircraft);

    $tour = UserTour::query()->where('user_id', $user->id)->firstOrFail();
    $legTwo = collect($tour->legs)->firstWhere('flight_id', $flights[1]->id);

    expect($tour->legs_completed)->toBe(2)
        ->and($tour->status)->toBe(TourStatus::InProgress)
        ->and($tour->pirep_id)->toBe($pirep->id)
        ->and($tour->flight_id)->toBe($flights[2]->id)
        ->and($legTwo['pirep_id'])->toBe($pirep->id)
        ->and($legTwo['filed_at'])->not->toBeNull()
        ->and(Bid::query()->where('user_id', $user->id)->pluck('flight_id')->sort()->values()->all())
        ->toBe($flights->slice(2)->pluck('id')->sort()->values()->all());
});

it('counts only the contiguous prefix when legs are filed out of order', function (): void {
    ['user' => $user, 'flights' => $flights, 'aircraft' => $aircraft] = makeTour(5);
    app(BidService::class)->addBid($flights[0], $user, $aircraft);

    $counts = [];

    foreach ([0, 2, 1] as $index) {
        fileTourLeg($user, $flights[$index], $aircraft);
        $counts[] = UserTour::query()->where('user_id', $user->id)->firstOrFail()->legs_completed;
    }

    expect($counts)->toBe([1, 1, 3]);
});

it('does not double count a re-filed leg', function (): void {
    ['user' => $user, 'flights' => $flights, 'aircraft' => $aircraft] = makeTour(4);
    app(BidService::class)->addBid($flights[0], $user, $aircraft);

    $pirep = fileTourLeg($user, $flights[0], $aircraft);
    app(PirepService::class)->submit($pirep);

    $tour = UserTour::query()->where('user_id', $user->id)->firstOrFail();

    expect($tour->legs_completed)->toBe(1)
        ->and($tour->status)->toBe(TourStatus::InProgress);
});

it('stays in progress while a middle leg is unflown', function (): void {
    ['user' => $user, 'flights' => $flights, 'aircraft' => $aircraft] = makeTour(5);
    app(BidService::class)->addBid($flights[0], $user, $aircraft);

    foreach ([0, 1, 2, 4] as $index) {
        fileTourLeg($user, $flights[$index], $aircraft);
    }

    $tour = UserTour::query()->where('user_id', $user->id)->firstOrFail();

    expect($tour->status)->toBe(TourStatus::InProgress)
        ->and($tour->legs_completed)->toBe(3)
        ->and($tour->flight_id)->toBe($flights[3]->id)
        ->and(Bid::query()->where('user_id', $user->id)->pluck('flight_id')->all())
        ->toBe([$flights[3]->id]);
});

it('points pirep_id at the in-progress PIREP when a leg is prefiled', function (): void {
    ['user' => $user, 'flights' => $flights, 'aircraft' => $aircraft] = makeTour(5);
    app(BidService::class)->addBid($flights[0], $user, $aircraft);

    fileTourLeg($user, $flights[0], $aircraft);
    fileTourLeg($user, $flights[1], $aircraft);

    $aircraft->refresh();
    $leg = $flights[2];
    $prefiled = app(PirepService::class)->prefile($user, [
        'airline_id'     => $leg->airline_id,
        'aircraft_id'    => $aircraft->id,
        'flight_id'      => $leg->id,
        'flight_number'  => $leg->flight_number,
        'dpt_airport_id' => $leg->dpt_airport_id,
        'arr_airport_id' => $leg->arr_airport_id,
        'status'         => PirepPhase::INITIATED,
    ]);

    $tour = UserTour::query()->where('user_id', $user->id)->firstOrFail();

    expect($tour->pirep_id)->toBe($prefiled->id)
        ->and($tour->legs_completed)->toBe(2)
        ->and($tour->flight_id)->toBe($leg->id);
});

it('completes the tour when the final leg is filed', function (): void {
    ['user' => $user, 'flights' => $flights, 'aircraft' => $aircraft] = makeTour(3);
    app(BidService::class)->addBid($flights[0], $user, $aircraft);

    $flights->each(fn (Flight $flight): Pirep => fileTourLeg($user, $flight, $aircraft));

    $tour = UserTour::query()->where('user_id', $user->id)->firstOrFail();

    expect($tour->status)->toBe(TourStatus::Completed)
        ->and($tour->completed_at)->not->toBeNull()
        ->and($tour->legs_completed)->toBe($tour->legs_total)
        ->and($tour->flight_id)->toBeNull()
        ->and(Bid::query()->where('user_tour_id', $tour->id)->count())->toBe(0);
});

it('leaves every tour alone for a PIREP outside one', function (): void {
    ['user' => $user, 'flights' => $flights, 'aircraft' => $aircraft] = makeTour(3);
    app(BidService::class)->addBid($flights[0], $user, $aircraft);

    $before = UserTour::query()->where('user_id', $user->id)->firstOrFail();

    $loose = Flight::factory()->create(['airline_id' => $user->airline_id]);
    fileTourLeg($user, $loose, $aircraft);

    $after = UserTour::query()->where('user_id', $user->id)->firstOrFail();

    expect($after->legs_completed)->toBe($before->legs_completed)
        ->and($after->pirep_id)->toBeNull()
        ->and($after->updated_at->toIso8601String())->toBe($before->updated_at->toIso8601String())
        ->and(Pirep::query()->where('flight_id', $loose->id)->count())->toBe(1);
});

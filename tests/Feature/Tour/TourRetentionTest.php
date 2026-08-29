<?php

declare(strict_types=1);

use App\Cron\Nightly\SetVisibleFlights;
use App\Enums\BundleType;
use App\Features\Tour\Enums\TourStatus;
use App\Features\Tour\Models\UserTour;
use App\Models\Bid;
use App\Models\FlightBundle;
use App\Models\User;
use App\Services\BidService;

beforeEach(function (): void {
    tourSettingsBaseline();
});

it('keeps the snapshotted name on a running tour when the bundle is renamed', function (): void {
    $bundle = FlightBundle::factory()->create([
        'type'        => BundleType::Tour,
        'name'        => 'Island Hopper',
        'description' => 'The original brief.',
    ]);
    ['user' => $user, 'flights' => $flights, 'aircraft' => $aircraft] = makeTour(3, null, $bundle);

    app(BidService::class)->addBid($flights[0], $user, $aircraft);

    $bundle->update(['name' => 'Pacific Milk Run', 'description' => 'Rewritten mid-flight.']);

    $running = UserTour::query()->where('user_id', $user->id)->firstOrFail();
    expect($running->name)->toBe('Island Hopper')
        ->and($running->description)->toBe('The original brief.');

    // A fresh run signs up for what the bundle says now. fresh() because a real
    // second request would load its own Flight; this test process holds one
    // with the old bundle still cached on the relation.
    $second = User::factory()->create();
    app(BidService::class)->addBid($flights[0]->fresh(), $second);

    $new = UserTour::query()->where('user_id', $second->id)->firstOrFail();
    expect($new->name)->toBe('Pacific Milk Run')
        ->and($new->description)->toBe('Rewritten mid-flight.');
});

it('still reads its snapshot when the bundle id no longer resolves', function (): void {
    $tour = UserTour::factory()->create([
        'bundle_id'   => 999_999_999,
        'name'        => 'Ghost Tour',
        'description' => 'Bundle long gone.',
    ]);

    $tour = UserTour::query()->with('bundle')->findOrFail($tour->id);

    expect($tour->bundle)->toBeNull()
        ->and($tour->name)->toBe('Ghost Tour')
        ->and($tour->description)->toBe('Bundle long gone.');
});

it('leaves an in-progress tour and its bids alone when the bundle passes its end date', function (): void {
    ['user' => $user, 'flights' => $flights, 'aircraft' => $aircraft, 'bundle' => $bundle] = makeTour(3);

    app(BidService::class)->addBid($flights[0], $user, $aircraft);
    fileTourLeg($user, $flights[0], $aircraft);

    $bundle->update([
        'start_date' => now('UTC')->subDays(30),
        'end_date'   => now('UTC')->subDay(),
    ]);
    SetVisibleFlights::run();

    $tour = UserTour::query()->where('user_id', $user->id)->firstOrFail();
    expect($tour->status)->toBe(TourStatus::InProgress)
        ->and(Bid::query()->where('user_id', $user->id)->count())->toBe(2)
        ->and($flights[1]->refresh()->visible)->toBeFalse();

    // The pilot can still fly and file the remaining legs.
    fileTourLeg($user, $flights[1], $aircraft);
    fileTourLeg($user, $flights[2], $aircraft);

    expect($tour->refresh()->status)->toBe(TourStatus::Completed);
});

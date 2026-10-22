<?php

declare(strict_types=1);

use App\Features\Tour\Enums\TourStatus;
use App\Features\Tour\Models\UserTour;
use App\Models\Bid;
use App\Models\FlightBundle;
use App\Models\User;

it('creates a run with a generated string key and cast columns', function (): void {
    $tour = UserTour::factory()->create();

    expect($tour->id)->toBeString()
        ->and($tour->exists)->toBeTrue()
        ->and($tour->status)->toBe(TourStatus::InProgress)
        ->and($tour->legs)->toBeArray()->toHaveCount(3)
        ->and($tour->legs[0]['route_leg'])->toBe(1)
        ->and($tour->started_at)->not->toBeNull();
});

it('has a state for each status', function (TourStatus $status, string $state): void {
    expect(UserTour::factory()->{$state}()->create()->status)->toBe($status);
})->with([
    [TourStatus::InProgress, 'inProgress'],
    [TourStatus::Completed, 'completed'],
    [TourStatus::Cancelled, 'cancelled'],
    [TourStatus::Expired, 'expired'],
]);

it('leaves a completed run with every leg filed and nothing left to fly', function (): void {
    $tour = UserTour::factory()->completed()->create();

    expect($tour->legs_completed)->toBe($tour->legs_total)
        ->and($tour->flight_id)->toBeNull()
        ->and($tour->completed_at)->not->toBeNull()
        ->and(collect($tour->legs)->pluck('pirep_id')->filter())->toHaveCount($tour->legs_total);
});

it('resolves its bundle while keeping its own snapshotted name', function (): void {
    $bundle = FlightBundle::factory()->create(['name' => 'Island Hopper']);
    $tour = UserTour::factory()->create([
        'bundle_id' => $bundle->id,
        'name'      => 'Island Hopper',
    ]);

    $bundle->update(['name' => 'Island Hopper 2026']);

    expect($tour->fresh()->name)->toBe('Island Hopper')
        ->and($tour->bundle->name)->toBe('Island Hopper 2026');
});

it('reads its name and description when the bundle no longer resolves', function (): void {
    $tour = UserTour::factory()->create([
        'bundle_id'   => 999999,
        'name'        => 'Orphaned Tour',
        'description' => 'Still readable.',
    ]);

    expect($tour->bundle)->toBeNull()
        ->and($tour->name)->toBe('Orphaned Tour')
        ->and($tour->description)->toBe('Still readable.');
});

it('links a bid to its tour run', function (): void {
    $tour = UserTour::factory()->create();
    $bid = Bid::factory()->create(['user_tour_id' => $tour->id]);

    expect($bid->userTour->id)->toBe($tour->id)
        ->and(Bid::factory()->create()->userTour)->toBeNull();
});

it('takes its bids with it when the run is deleted', function (): void {
    $tour = UserTour::factory()->create();
    $bid = Bid::factory()->create(['user_tour_id' => $tour->id]);

    $tour->delete();

    expect(Bid::find($bid->id))->toBeNull();
});

it('cascades to the run when a pilot is hard-deleted', function (): void {
    $user = User::factory()->create();
    $tour = UserTour::factory()->create(['user_id' => $user->id]);

    $user->forceDelete();

    expect(UserTour::find($tour->id))->toBeNull();
});

it('keeps the run when a pilot is only soft-deleted', function (): void {
    $user = User::factory()->create();
    $tour = UserTour::factory()->create(['user_id' => $user->id]);

    $user->delete();

    expect(UserTour::find($tour->id))->not->toBeNull();
});

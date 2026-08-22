<?php

declare(strict_types=1);

use App\Enums\BundleType;
use App\Exceptions\BidExistsForFlight;
use App\Exceptions\UserBidLimit;
use App\Features\Tour\Enums\TourStatus;
use App\Features\Tour\Models\UserTour;
use App\Models\Bid;
use App\Models\Flight;
use App\Models\FlightBundle;
use App\Models\User;
use App\Services\BidService;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    tourSettingsBaseline();
});

it('creates one bid per leg under a single tour row', function (): void {
    ['user' => $user, 'flights' => $flights, 'aircraft' => $aircraft] = makeTour(5);

    app(BidService::class)->addBid($flights[0], $user, $aircraft);

    $bids = Bid::query()->where('user_id', $user->id)->get();
    $tour = UserTour::query()->where('user_id', $user->id)->firstOrFail();

    expect($bids)->toHaveCount(5)
        ->and($bids->pluck('user_tour_id')->unique()->all())->toBe([$tour->id])
        ->and($bids->pluck('aircraft_id')->unique()->all())->toBe([$aircraft->id])
        ->and($tour->status)->toBe(TourStatus::InProgress)
        ->and($tour->legs_total)->toBe(5)
        ->and($tour->legs_completed)->toBe(0)
        ->and($tour->started_at)->not->toBeNull()
        ->and($tour->flight_id)->toBe($flights[0]->id)
        ->and($tour->legs)->toHaveCount(5)
        ->and(collect($tour->legs)->pluck('route_leg')->all())->toBe([1, 2, 3, 4, 5])
        ->and(collect($tour->legs)->pluck('pirep_id')->filter()->all())->toBe([]);
});

it('snapshots the bundle name and description onto the run', function (): void {
    $bundle = FlightBundle::factory()->create([
        'type'        => BundleType::Tour,
        'name'        => 'Alpine Circuit',
        'description' => 'Five legs through the Alps.',
    ]);
    ['user' => $user, 'flights' => $flights, 'aircraft' => $aircraft] = makeTour(3, null, $bundle);

    app(BidService::class)->addBid($flights[0], $user, $aircraft);

    $tour = UserTour::query()->where('user_id', $user->id)->firstOrFail();

    expect($tour->name)->toBe('Alpine Circuit')
        ->and($tour->description)->toBe('Five legs through the Alps.');
});

it('starts the tour from leg 1 but returns the leg the pilot named', function (): void {
    ['user' => $user, 'flights' => $flights, 'aircraft' => $aircraft] = makeTour(5);

    $bid = app(BidService::class)->addBid($flights[2], $user, $aircraft);

    expect($bid->flight_id)->toBe($flights[2]->id)
        ->and(Bid::query()->where('user_id', $user->id)->pluck('flight_id')->sort()->values()->all())
        ->toBe($flights->pluck('id')->sort()->values()->all());
});

it('returns the existing bid when the pilot re-bids a running tour', function (): void {
    ['user' => $user, 'flights' => $flights, 'aircraft' => $aircraft] = makeTour(4);

    $first = app(BidService::class)->addBid($flights[0], $user, $aircraft);
    $again = app(BidService::class)->addBid($flights[2], $user, $aircraft);

    expect($again->flight_id)->toBe($flights[2]->id)
        ->and($again->user_tour_id)->toBe($first->user_tour_id)
        ->and(Bid::query()->where('user_id', $user->id)->count())->toBe(4)
        ->and(UserTour::query()->where('user_id', $user->id)->count())->toBe(1);
});

it('creates every leg when multiple bids are disallowed and the pilot has none', function (): void {
    updateSetting('bids.allow_multiple_bids', false);
    ['user' => $user, 'flights' => $flights, 'aircraft' => $aircraft] = makeTour(4);

    app(BidService::class)->addBid($flights[0], $user, $aircraft);

    expect(Bid::query()->where('user_id', $user->id)->count())->toBe(4);
});

it('refuses the tour when the pilot already holds an ordinary bid', function (): void {
    ['user' => $user, 'flights' => $flights, 'aircraft' => $aircraft] = makeTour(4);
    $other = Flight::factory()->create(['airline_id' => $user->airline_id]);
    app(BidService::class)->addBid($other, $user);

    updateSetting('bids.allow_multiple_bids', false);

    expect(fn () => app(BidService::class)->addBid($flights[0], $user, $aircraft))
        ->toThrow(UserBidLimit::class);

    expect(Bid::query()->where('user_id', $user->id)->count())->toBe(1)
        ->and(UserTour::query()->count())->toBe(0);
});

it('creates the tour when another pilot holds a downstream leg', function (): void {
    ['user' => $user, 'flights' => $flights, 'aircraft' => $aircraft, 'subfleet' => $subfleet] = makeTour(5);

    $rival = User::factory()->create(['airline_id' => $user->airline_id]);
    $rivalBid = Bid::query()->create(['user_id' => $rival->id, 'flight_id' => $flights[2]->id]);

    updateSetting('bids.disable_flight_on_bid', true);

    app(BidService::class)->addBid($flights[0], $user, $aircraft);

    expect(Bid::query()->where('user_id', $user->id)->count())->toBe(5)
        ->and(Bid::query()->whereKey($rivalBid->id)->exists())->toBeTrue()
        ->and($subfleet->exists)->toBeTrue();
});

it('refuses the tour when another pilot already holds leg 1', function (): void {
    ['user' => $user, 'flights' => $flights, 'aircraft' => $aircraft] = makeTour(3);

    $rival = User::factory()->create(['airline_id' => $user->airline_id]);
    Bid::query()->create(['user_id' => $rival->id, 'flight_id' => $flights[0]->id]);

    updateSetting('bids.disable_flight_on_bid', true);

    expect(fn () => app(BidService::class)->addBid($flights[0], $user, $aircraft))
        ->toThrow(BidExistsForFlight::class);

    expect(UserTour::query()->count())->toBe(0)
        ->and(Bid::query()->where('user_id', $user->id)->count())->toBe(0);
});

it('creates downstream legs even though the aircraft is only at leg 1', function (): void {
    updateSetting('pireps.only_aircraft_at_dpt_airport', true);
    ['user' => $user, 'flights' => $flights, 'aircraft' => $aircraft] = makeTour(4);

    app(BidService::class)->addBid($flights[0], $user, $aircraft);

    expect(Bid::query()->where('user_id', $user->id)->count())->toBe(4)
        ->and(UserTour::query()->where('user_id', $user->id)->count())->toBe(1);
});

it('rejects a tour with no aircraft when block_aircraft is on', function (): void {
    updateSetting('bids.block_aircraft', true);
    ['user' => $user, 'flights' => $flights] = makeTour(3);

    expect(fn () => app(BidService::class)->addBid($flights[0], $user))
        ->toThrow(ValidationException::class);

    expect(Bid::query()->count())->toBe(0)
        ->and(UserTour::query()->count())->toBe(0);
});

it('rejects a tour whose legs skip a number', function (): void {
    ['user' => $user, 'flights' => $flights, 'aircraft' => $aircraft] = makeTour(3);
    $flights[2]->update(['route_leg' => 4]);

    expect(fn () => app(BidService::class)->addBid($flights[0], $user, $aircraft))
        ->toThrow(ValidationException::class);

    expect(Bid::query()->count())->toBe(0)
        ->and(UserTour::query()->count())->toBe(0);
});

it('rejects a tour whose flights carry no leg number', function (): void {
    ['user' => $user, 'flights' => $flights, 'aircraft' => $aircraft] = makeTour(3);
    $flights->each(fn (Flight $flight) => $flight->update(['route_leg' => null]));

    expect(fn () => app(BidService::class)->addBid($flights[0], $user, $aircraft))
        ->toThrow(ValidationException::class);

    expect(Bid::query()->count())->toBe(0)
        ->and(UserTour::query()->count())->toBe(0);
});

it('marks every leg as having a bid', function (): void {
    ['user' => $user, 'flights' => $flights, 'aircraft' => $aircraft] = makeTour(3);

    app(BidService::class)->addBid($flights[0], $user, $aircraft);

    expect(Flight::query()->whereIn('id', $flights->pluck('id'))->pluck('has_bid')->all())
        ->toBe([true, true, true]);
});

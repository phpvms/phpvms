<?php

declare(strict_types=1);

use App\Cron\Hourly\RemoveExpiredBids;
use App\Enums\PirepFieldSource;
use App\Enums\PirepState;
use App\Events\CronHourly;
use App\Features\Tour\Enums\TourStatus;
use App\Features\Tour\Models\UserTour;
use App\Models\Airport;
use App\Models\Bid;
use App\Models\Flight;
use App\Models\Pirep;
use App\Models\User;
use App\Services\BidService;
use App\Services\FlightService;
use App\Services\PirepService;
use App\Services\UserService;
use Carbon\Carbon;

beforeEach(function (): void {
    tourSettingsBaseline();
});

it('cancels the whole run when the pilot drops one leg', function (): void {
    ['user' => $user, 'flights' => $flights, 'aircraft' => $aircraft] = makeTour(5);
    app(BidService::class)->addBid($flights[0], $user, $aircraft);
    fileTourLeg($user, $flights[0], $aircraft);

    $this->actingAs($user)
        ->delete(route('frontend.flights.bid.destroy', $flights[2]->id))
        ->assertOk();

    $tour = UserTour::query()->where('user_id', $user->id)->firstOrFail();

    expect($tour->status)->toBe(TourStatus::Cancelled)
        ->and($tour->legs_completed)->toBe(1)
        ->and(collect($tour->legs)->firstWhere('route_leg', 1)['pirep_id'])->not->toBeNull()
        ->and(Bid::query()->where('user_id', $user->id)->count())->toBe(0);
});

it('leaves other pilots alone and recomputes has_bid per flight', function (): void {
    ['user' => $user, 'flights' => $flights, 'aircraft' => $aircraft] = makeTour(3);
    app(BidService::class)->addBid($flights[0], $user, $aircraft);

    $rival = User::factory()->create(['airline_id' => $user->airline_id]);
    Bid::query()->create(['user_id' => $rival->id, 'flight_id' => $flights[1]->id]);

    app(BidService::class)->removeBidsForUser($user);

    expect(UserTour::query()->where('user_id', $user->id)->firstOrFail()->status)
        ->toBe(TourStatus::Cancelled)
        ->and(Bid::query()->where('user_id', $rival->id)->count())->toBe(1)
        ->and(Flight::query()->whereIn('id', $flights->pluck('id'))->pluck('has_bid', 'id')->all())
        ->toEqual([
            $flights[0]->id => false,
            $flights[1]->id => true,
            $flights[2]->id => false,
        ]);
});

it('expires the whole tour but ordinary bids one at a time', function (): void {
    updateSetting('bids.expire_time', 1);
    ['user' => $user, 'flights' => $flights, 'aircraft' => $aircraft] = makeTour(4);
    app(BidService::class)->addBid($flights[0], $user, $aircraft);

    $loose = Flight::factory()->create(['airline_id' => $user->airline_id]);
    app(BidService::class)->addBid($loose, $user);

    // Only leg 1 and the ordinary bid have aged; the rest of the tour goes with
    // leg 1 rather than one leg at a time over the following hours.
    Bid::query()->whereIn('flight_id', [$flights[0]->id, $loose->id])
        ->update(['created_at' => Carbon::now('UTC')->subHours(5)]);

    app(RemoveExpiredBids::class)->handle(new CronHourly());

    expect(UserTour::query()->where('user_id', $user->id)->firstOrFail()->status)
        ->toBe(TourStatus::Expired)
        ->and(Bid::query()->where('user_id', $user->id)->count())->toBe(0);
});

it('lets a pilot restart a cancelled tour without touching the old row', function (): void {
    ['user' => $user, 'flights' => $flights, 'aircraft' => $aircraft] = makeTour(3);
    app(BidService::class)->addBid($flights[0], $user, $aircraft);

    $first = UserTour::query()->where('user_id', $user->id)->firstOrFail();
    app(BidService::class)->removeBidsForUser($user);

    app(BidService::class)->addBid($flights[0], $user, $aircraft);

    $tours = UserTour::query()->where('user_id', $user->id)->get();

    expect($tours)->toHaveCount(2)
        ->and($tours->firstWhere('id', $first->id)->status)->toBe(TourStatus::Cancelled)
        ->and($tours->where('status', TourStatus::InProgress))->toHaveCount(1);
});

it('cancels the tour when a leg diverts', function (): void {
    updateSetting('pireps.handle_diversion', true);
    ['user' => $user, 'flights' => $flights, 'aircraft' => $aircraft] = makeTour(5);
    app(BidService::class)->addBid($flights[0], $user, $aircraft);

    fileTourLeg($user, $flights[0], $aircraft);

    $diversion = Airport::factory()->create();
    $pirepSvc = app(PirepService::class);
    $pirep = Pirep::factory()->create([
        'user_id'        => $user->id,
        'flight_id'      => $flights[1]->id,
        'airline_id'     => $flights[1]->airline_id,
        'aircraft_id'    => $aircraft->id,
        'dpt_airport_id' => $flights[1]->dpt_airport_id,
        'arr_airport_id' => $flights[1]->arr_airport_id,
        'route_leg'      => 2,
        'state'          => PirepState::PENDING,
    ]);
    $pirepSvc->create($pirep, [
        ['name' => 'Diversion Airport', 'value' => $diversion->id, 'source' => PirepFieldSource::ACARS],
    ]);
    $pirepSvc->submit($pirep);

    $tour = UserTour::query()->where('user_id', $user->id)->firstOrFail();

    expect($tour->status)->toBe(TourStatus::Cancelled)
        ->and($tour->legs_completed)->toBe(1)
        ->and(collect($tour->legs)->firstWhere('route_leg', 1)['pirep_id'])->not->toBeNull()
        ->and(collect($tour->legs)->firstWhere('route_leg', 2)['pirep_id'])->toBeNull()
        ->and(Bid::query()->where('user_id', $user->id)->count())->toBe(0);
});

it('cancels a live tour when a filed leg is rejected but leaves a completed one alone', function (): void {
    ['user' => $user, 'flights' => $flights, 'aircraft' => $aircraft] = makeTour(3);
    app(BidService::class)->addBid($flights[0], $user, $aircraft);
    $pirep = fileTourLeg($user, $flights[0], $aircraft);

    app(PirepService::class)->reject($pirep);

    expect(UserTour::query()->where('user_id', $user->id)->firstOrFail()->status)
        ->toBe(TourStatus::Cancelled);

    // A second pilot runs the tour to completion; rejecting one of its legs
    // afterwards must not reopen or re-close a terminal run.
    ['user' => $other, 'flights' => $otherFlights, 'aircraft' => $otherAircraft] = makeTour(2);
    app(BidService::class)->addBid($otherFlights[0], $other, $otherAircraft);
    $first = fileTourLeg($other, $otherFlights[0], $otherAircraft);
    fileTourLeg($other, $otherFlights[1], $otherAircraft);

    app(PirepService::class)->reject($first);

    expect(UserTour::query()->where('user_id', $other->id)->firstOrFail()->status)
        ->toBe(TourStatus::Completed);
});

it('cancels the tour when a leg PIREP is deleted', function (): void {
    ['user' => $user, 'flights' => $flights, 'aircraft' => $aircraft] = makeTour(3);
    app(BidService::class)->addBid($flights[0], $user, $aircraft);
    $pirep = fileTourLeg($user, $flights[0], $aircraft);

    app(PirepService::class)->delete($pirep);

    expect(UserTour::query()->where('user_id', $user->id)->firstOrFail()->status)
        ->toBe(TourStatus::Cancelled);
});

it('leaves the tour in progress when an admin deletes one of its flights', function (): void {
    ['user' => $user, 'flights' => $flights, 'aircraft' => $aircraft] = makeTour(4);
    app(BidService::class)->addBid($flights[0], $user, $aircraft);

    app(FlightService::class)->deleteFlight($flights[2]);

    expect(UserTour::query()->where('user_id', $user->id)->firstOrFail()->status)
        ->toBe(TourStatus::InProgress)
        ->and(Bid::query()->where('user_id', $user->id)->count())->toBe(3);
});

it('keeps a soft-deleted pilot tours but ends the live ones', function (): void {
    ['user' => $user, 'flights' => $flights, 'aircraft' => $aircraft] = makeTour(3);
    app(BidService::class)->addBid($flights[0], $user, $aircraft);
    fileTourLeg($user, $flights[0], $aircraft);

    app(UserService::class)->removeUser($user->refresh());

    $tour = UserTour::query()->where('user_id', $user->id)->firstOrFail();

    expect($tour->status)->toBe(TourStatus::Cancelled)
        ->and($tour->legs_completed)->toBe(1);
});

it('removes the tour rows of a hard-deleted pilot', function (): void {
    ['user' => $user, 'flights' => $flights, 'aircraft' => $aircraft] = makeTour(3);
    app(BidService::class)->addBid($flights[0], $user, $aircraft);

    app(UserService::class)->removeUser($user);

    expect(UserTour::query()->where('user_id', $user->id)->count())->toBe(0);
});

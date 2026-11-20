<?php

declare(strict_types=1);

use App\Hooks\Slots\SlotBidDrawerActions;
use App\Hooks\Slots\SlotBidRowActions;
use App\Hooks\Slots\SlotBriefingActions;
use App\Hooks\Slots\SlotDashboardReadyActions;
use App\Hooks\Slots\SlotDashboardReadyDetails;
use App\Hooks\Slots\SlotFlightActions;
use App\Hooks\Slots\SlotFlightRowActions;
use App\Hooks\Slots\SlotPirepDetail;
use App\Hooks\Slots\SlotProfileCards;
use App\Http\Data\BidData;
use App\Http\Data\BidSelectionData;
use App\Http\Data\EligibleAircraftData;
use App\Http\Data\FlightDetailData;
use App\Http\Data\FlightDispatchPolicyData;
use App\Http\Data\FlightListItemData;
use App\Http\Data\ProfileData;
use App\Http\Data\SimBriefBriefingData;
use App\Models\Airport;
use App\Models\Flight;
use App\Models\Pirep;
use App\Models\User;

/**
 * Build a DTO without its constructor; these tests only pass it through.
 *
 * @template T of object
 *
 * @param  class-string<T> $class
 * @return T
 */
function bare(string $class): object
{
    return new ReflectionClass($class)->newInstanceWithoutConstructor();
}

it('uses the slot names the SPA outlets read', function (): void {
    expect(SlotBriefingActions::name())->toBe('flight.briefing.actions')
        ->and(SlotPirepDetail::name())->toBe('pireps.detail.main')
        ->and(SlotProfileCards::name())->toBe('profile.cards')
        ->and(SlotFlightActions::name())->toBe('flights.detail.actions')
        ->and(SlotBidDrawerActions::name())->toBe('bids.drawer.overview.actions')
        ->and(SlotFlightRowActions::name())->toBe('flights.row.actions')
        ->and(SlotBidRowActions::name())->toBe('bids.row.actions');
});

it('names the airside-only dashboard points', function (): void {
    expect(SlotDashboardReadyDetails::name())->toBe('dashboard.ready.details')
        ->and(SlotDashboardReadyActions::name())->toBe('dashboard.ready.actions');
});

it('builds the flight actions point for one flight and for a list', function (): void {
    $flight = bare(FlightListItemData::class);
    $policy = bare(FlightDispatchPolicyData::class);
    $user = new User();

    $one = new SlotFlightActions($flight, $policy, $user, null, null);
    $list = SlotFlightActions::forList($policy, $user);

    expect($one->isList())->toBeFalse()
        ->and($one->flight)->toBe($flight)
        ->and($list->isList())->toBeTrue()
        ->and($list->flight)->toBeNull()
        ->and($list->bid)->toBeNull()
        ->and($list->briefing)->toBeNull()
        ->and($list->policy)->toBe($policy)
        ->and($list->user)->toBe($user)
        ->and($list->value)->toBe([]);
});

it('builds the bid drawer point for one bid and for a list', function (): void {
    $bid = bare(BidData::class);
    $selection = bare(BidSelectionData::class);

    $one = new SlotBidDrawerActions($bid, $selection);
    $list = SlotBidDrawerActions::forList();

    expect($one->isList())->toBeFalse()
        ->and($one->selection)->toBe($selection)
        ->and($list->isList())->toBeTrue()
        ->and($list->bid)->toBeNull()
        ->and($list->selection)->toBeNull();
});

it('builds the flight row point for one row and for a list', function (): void {
    $flight = bare(FlightListItemData::class);
    $policy = bare(FlightDispatchPolicyData::class);

    $one = new SlotFlightRowActions($flight, $policy);
    $list = SlotFlightRowActions::forList($policy);

    expect($one->isList())->toBeFalse()
        ->and($list->isList())->toBeTrue()
        ->and($list->flight)->toBeNull()
        ->and($list->policy)->toBe($policy);
});

it('builds the bid row point for one row and for a list', function (): void {
    $bid = bare(BidData::class);
    $flight = bare(FlightListItemData::class);

    $one = new SlotBidRowActions($bid, $flight);
    $list = SlotBidRowActions::forList();

    expect($one->isList())->toBeFalse()
        ->and($one->flight)->toBe($flight)
        ->and($list->isList())->toBeTrue()
        ->and($list->bid)->toBeNull()
        ->and($list->flight)->toBeNull();
});

it('carries the page values on the page-level points', function (): void {
    $pirep = new Pirep();
    $profile = bare(ProfileData::class);
    $briefing = bare(SimBriefBriefingData::class);
    $airport = new Airport();
    $user = new User();

    expect(new SlotPirepDetail($pirep)->pirep)->toBe($pirep)
        ->and(new SlotProfileCards($profile)->profile)->toBe($profile)
        ->and(new SlotBriefingActions(bare(FlightDetailData::class), null, $briefing, bare(EligibleAircraftData::class))->briefing)->toBe($briefing)
        ->and(new SlotDashboardReadyDetails($airport, $user, null)->pirep)->toBeNull()
        ->and(new SlotDashboardReadyDetails(null, $user, null)->airport)->toBeNull()
        ->and(new SlotDashboardReadyActions($airport, $user)->airport)->toBe($airport);
});

it('rejects a model where a flight DTO is required', function (): void {
    app()->make(SlotFlightRowActions::class, ['flight' => new Flight(), 'policy' => bare(FlightDispatchPolicyData::class)]);
})->throws(TypeError::class);

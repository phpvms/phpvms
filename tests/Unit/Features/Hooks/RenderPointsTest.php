<?php

declare(strict_types=1);

use App\Features\Hooks\Points\App\Slots\SlotBidDrawerActions;
use App\Features\Hooks\Points\App\Slots\SlotBidRowActions;
use App\Features\Hooks\Points\App\Slots\SlotBriefingActions;
use App\Features\Hooks\Points\App\Slots\SlotDashboardReadyActions;
use App\Features\Hooks\Points\App\Slots\SlotDashboardReadyDetails;
use App\Features\Hooks\Points\App\Slots\SlotFlightActions;
use App\Features\Hooks\Points\App\Slots\SlotFlightRowActions;
use App\Features\Hooks\Points\App\Slots\SlotPirepDetail;
use App\Features\Hooks\Points\App\Slots\SlotProfileCards;
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
    $spa = '';

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path('resources/js/apps/fe-vue/src'), FilesystemIterator::SKIP_DOTS)) as $file) {
        if (in_array($file->getExtension(), ['vue', 'ts'], true)) {
            $spa .= file_get_contents($file->getPathname());
        }
    }

    expect(SlotBriefingActions::name())->toBe('flight.briefing.actions')
        ->and(SlotPirepDetail::name())->toBe('pireps.detail.main')
        ->and(SlotProfileCards::name())->toBe('profile.cards')
        ->and(SlotFlightActions::name())->toBe('flights.detail.actions')
        ->and(SlotBidDrawerActions::name())->toBe('bids.drawer.overview.actions')
        ->and(SlotFlightRowActions::name())->toBe('flights.row.actions')
        ->and(SlotBidRowActions::name())->toBe('bids.row.actions');

    foreach (['flight.briefing.actions', 'pireps.detail.main', 'profile.cards', 'flights.detail.actions', 'bids.drawer.overview.actions', 'flights.row.actions', 'bids.row.actions'] as $name) {
        expect($spa)->toContain($name);
    }
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

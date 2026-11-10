<?php

declare(strict_types=1);

use App\Enums\BriefingItemKind;
use App\Enums\BriefingStationRole;
use App\Features\Flights\Actions\BuildFlightBriefing;
use App\Features\Hooks\Facades\Hooks;
use App\Features\Hooks\Points\App\Filters\FilterBriefingTimeline;
use App\Http\Data\BriefingTimelineItemData;
use App\Models\Airport;
use App\Models\Flight;
use App\Models\SimBrief;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class AddsBriefingNotice
{
    /**
     * @return list<BriefingTimelineItemData>
     */
    public function handle(FilterBriefingTimeline $hook): array
    {
        return [
            ...$hook->value,
            new BriefingTimelineItemData(
                kind: BriefingItemKind::Notice,
                anchor: $hook->flight->dpt_airport_id,
                title: 'Volcanic ash advisory',
                source: 'ash-addon',
            ),
        ];
    }
}

final class MutatesBriefingItems
{
    public function handle(FilterBriefingTimeline $hook): array
    {
        $hook->value[0]->title = 'Changed';

        return $hook->value;
    }
}

/**
 * @param array<string, mixed> $flightAttributes
 */
function briefingFlight(array $flightAttributes = []): Flight
{
    $departure = Airport::factory()->create(['icao' => 'OMAA', 'name' => 'Abu Dhabi']);
    $arrival = Airport::factory()->create(['icao' => 'OMDB', 'name' => 'Dubai']);

    return Flight::factory()->create([
        'dpt_airport_id' => $departure->id,
        'arr_airport_id' => $arrival->id,
        'route'          => 'DCT TEST',
        ...$flightAttributes,
    ]);
}

/**
 * A SimBrief row for the pilot with the fixture OFP: an origin entry added in
 * front of the navlog, and a step climb at LORID.
 */
function briefingOfpFor(User $user, Flight $flight): SimBrief
{
    $ofp = json_decode(readDataFile('simbrief/briefing.json'), true);
    $navlog = $ofp['navlog'];
    $origin = $navlog[0];
    $origin['ident'] = 'OMAA';
    $origin['time_total'] = '00:00:00';
    array_unshift($navlog, $origin);

    foreach ($navlog as &$fix) {
        if ($fix['ident'] === 'LORID') {
            $fix['altitude_feet'] = '11000';
        }
    }

    unset($fix);

    $ofp['navlog'] = $navlog;

    $path = 'simbrief/briefing-flight-'.Str::uuid().'.json';
    Storage::put($path, json_encode($ofp));

    return SimBrief::factory()->create([
        'id'            => Str::uuid()->toString(),
        'user_id'       => $user->id,
        'flight_id'     => $flight->id,
        'pirep_id'      => null,
        'ofp_json_path' => $path,
    ]);
}

test('without an OFP the timeline is departure, route, arrival and alternate', function (): void {
    $alternate = Airport::factory()->create(['icao' => 'OMSJ', 'name' => 'Sharjah']);
    $flight = briefingFlight(['alt_airport_id' => $alternate->id]);

    $briefing = app(BuildFlightBriefing::class)->handle($flight, User::factory()->create());

    expect(array_map(fn (BriefingTimelineItemData $item): array => [$item->kind, $item->anchor, $item->title, $item->detail], $briefing->timeline))
        ->toBe([
            [BriefingItemKind::Station, 'OMAA', 'Abu Dhabi', 'Departure'],
            [BriefingItemKind::Route, null, 'DCT TEST', null],
            [BriefingItemKind::Station, 'OMDB', 'Dubai', 'Arrival'],
            [BriefingItemKind::Station, 'OMSJ', 'Sharjah', 'Alternate'],
        ])
        ->and(array_map(fn (BriefingTimelineItemData $item): ?BriefingStationRole => $item->role, $briefing->timeline))
        ->toBe([BriefingStationRole::Departure, null, BriefingStationRole::Arrival, BriefingStationRole::Alternate])
        ->and($briefing->plannedFixes)->toBe([])
        ->and($briefing->ofp)->toBeNull();
});

test('a flight without a route or alternate has no route or alternate row', function (): void {
    $briefing = app(BuildFlightBriefing::class)->handle(briefingFlight(['route' => '', 'alt_airport_id' => null]), User::factory()->create());

    expect(array_map(fn (BriefingTimelineItemData $item): BriefingItemKind => $item->kind, $briefing->timeline))
        ->toBe([BriefingItemKind::Station, BriefingItemKind::Station]);
});

test('an OFP turns TOC and TOD into phases, drops the airports and marks step climbs', function (): void {
    $user = User::factory()->create();
    $flight = briefingFlight();
    briefingOfpFor($user, $flight);

    $briefing = app(BuildFlightBriefing::class)->handle($flight, $user);
    $rows = collect($briefing->timeline);

    $phases = $rows->where('kind', BriefingItemKind::Phase);
    expect($phases->pluck('title')->values()->all())->toBe(['Top of climb', 'Top of descent'])
        ->and($rows->where('kind', BriefingItemKind::Fix)->pluck('anchor')->contains('TOC'))->toBeFalse()
        ->and($rows->where('kind', BriefingItemKind::Station)->first()->anchor)->toBe('OMAA')
        ->and($rows->whereIn('anchor', ['OMAA', 'OMDB'])->where('kind', '!=', BriefingItemKind::Station))->toBeEmpty()
        ->and($rows->first(fn (BriefingTimelineItemData $item): bool => $item->anchor === 'LORID')->detail)->toBe('Step climb to FL110')
        ->and($rows->first(fn (BriefingTimelineItemData $item): bool => $item->anchor === 'OBMUK')->detail)->toBeNull()
        ->and($rows->first(fn (BriefingTimelineItemData $item): bool => $item->anchor === 'TOC')->offsetMinutes)->toBe(2)
        ->and($briefing->plannedFixes)->not->toBeEmpty()
        ->and($briefing->ofp->weightUnit)->toBe('kg');
});

test('departure is at offset 0 and the arrival at the OFP time enroute, else without an offset', function (): void {
    $user = User::factory()->create();
    $flight = briefingFlight();

    $stationOffsets = fn (): array => collect(app(BuildFlightBriefing::class)->handle($flight, $user)->timeline)
        ->whereIn('role', [BriefingStationRole::Departure, BriefingStationRole::Arrival])
        ->mapWithKeys(fn (BriefingTimelineItemData $item): array => [$item->role->value => $item->offsetMinutes])
        ->all();

    expect($stationOffsets())->toBe([BriefingStationRole::Departure->value => 0, BriefingStationRole::Arrival->value => null]);

    briefingOfpFor($user, $flight);

    expect($stationOffsets())->toBe([BriefingStationRole::Departure->value => 0, BriefingStationRole::Arrival->value => 37]);
});

test("another pilot's OFP is not used", function (): void {
    $flight = briefingFlight();
    briefingOfpFor(User::factory()->create(), $flight);

    $briefing = app(BuildFlightBriefing::class)->handle($flight, User::factory()->create());

    expect($briefing->ofp)->toBeNull()
        ->and($briefing->plannedFixes)->toBe([])
        ->and(collect($briefing->timeline)->where('kind', BriefingItemKind::Phase))->toBeEmpty();
});

test('an addon notice added through the hook is in the result', function (): void {
    Hooks::listen(FilterBriefingTimeline::class, AddsBriefingNotice::class);

    $briefing = app(BuildFlightBriefing::class)->handle(briefingFlight(), User::factory()->create());

    $notice = collect($briefing->timeline)->firstWhere('kind', BriefingItemKind::Notice);
    expect($notice)->not->toBeNull()
        ->and($notice->source)->toBe('ash-addon')
        ->and($notice->anchor)->toBe('OMAA');
});

test('a listener cannot change an item in place', function (): void {
    Hooks::listen(FilterBriefingTimeline::class, MutatesBriefingItems::class);

    $briefing = app(BuildFlightBriefing::class)->handle(briefingFlight(), User::factory()->create());

    expect($briefing->timeline[0]->title)->toBe('Abu Dhabi');
});

test('step climbs are found without a top of climb from the first enroute fix', function (): void {
    $user = User::factory()->create();
    $flight = briefingFlight();
    $brief = briefingOfpFor($user, $flight);

    $ofp = json_decode(Storage::get($brief->ofp_json_path), true);
    $ofp['navlog'] = array_values(array_filter($ofp['navlog'], fn (array $fix): bool => $fix['ident'] !== 'TOC'));
    Storage::put($brief->ofp_json_path, json_encode($ofp));

    $rows = collect(app(BuildFlightBriefing::class)->handle($flight, $user)->timeline);

    expect($rows->first(fn (BriefingTimelineItemData $item): bool => $item->anchor === 'LORID')->detail)->toBe('Step climb to FL110');
});

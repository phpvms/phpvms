<?php

declare(strict_types=1);

use App\Cron\Hourly\RemoveExpiredBids;
use App\Enums\AircraftState;
use App\Enums\AircraftStatus;
use App\Enums\FareType;
use App\Events\CronHourly;
use App\Http\Data\BidSelectionData;
use App\Http\Data\FlightDispatchPolicyData;
use App\Models\Aircraft;
use App\Models\Airline;
use App\Models\Airport;
use App\Models\Bid;
use App\Models\Fare;
use App\Models\Flight;
use App\Models\Pirep;
use App\Models\Setting;
use App\Models\SimBrief;
use App\Models\SimBriefAttempt;
use App\Models\Subfleet;
use App\Models\User;
use App\Services\BidService;
use App\Services\FlightService;
use App\Services\SettingService;
use App\Services\SkylightSimBriefService;
use App\Services\UserService;
use Carbon\Carbon;
use Igaster\LaravelTheme\Facades\Theme;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    Theme::set('skylight');
    updateSetting('units.distance', 'nmi');
    updateSetting('pilots.restrict_to_company', false);
    updateSetting('pilots.only_show_flights_from_current', false);
    updateSetting('pilots.only_flights_from_current', false);
    updateSetting('pireps.restrict_aircraft_to_rank', false);
    updateSetting('pireps.restrict_aircraft_to_typerating', false);
    updateSetting('pireps.only_aircraft_at_dpt_airport', false);
    updateSetting('flights.only_company_aircraft', false);
    updateSetting('bids.disable_flight_on_bid', false);
    updateSetting('bids.allow_multiple_bids', true);
    updateSetting('bids.block_aircraft', false);
    updateSetting('bids.expire_time', 0);
    updateSetting('simbrief.block_aircraft', false);
    updateSetting('simbrief.only_bids', false);
});

function makeDispatchFixture(?User $user = null): array
{
    $departure = Airport::factory()->create();
    $arrival = Airport::factory()->create();
    $user ??= User::factory()->create(['curr_airport_id' => $departure->id]);
    $airline = Airline::query()->findOrFail($user->airline_id);
    $subfleet = Subfleet::factory()->create(['airline_id' => $airline->id]);
    $aircraft = Aircraft::factory()->create([
        'subfleet_id' => $subfleet->id,
        'airport_id'  => $departure->id,
    ]);
    $flight = Flight::factory()->hasAttached($subfleet)->create([
        'airline_id'     => $airline->id,
        'dpt_airport_id' => $departure->id,
        'arr_airport_id' => $arrival->id,
        'route'          => 'DCT TEST',
    ]);

    return compact('user', 'airline', 'subfleet', 'aircraft', 'flight', 'departure', 'arrival');
}

it('returns typed drawer data only to an authenticated pilot', function (): void {
    $fixture = makeDispatchFixture();

    $this->getJson('/flights/'.$fixture['flight']->id.'/dispatch')->assertUnauthorized();

    $this->actingAs($fixture['user'])
        ->getJson('/flights/'.$fixture['flight']->id.'/dispatch')
        ->assertOk()
        ->assertJsonPath('flight.summary.id', $fixture['flight']->id)
        ->assertJsonPath('policy.aircraftRequired', false)
        ->assertJsonPath('subfleets.0.id', $fixture['subfleet']->id)
        ->assertJsonPath('subfleets.0.eligibleAircraftCount', 1)
        ->assertJsonPath('selection', null);
});

it('keeps configured zero-result subfleets visible and loads aircraft only by subfleet', function (): void {
    $fixture = makeDispatchFixture();
    $emptySubfleet = Subfleet::factory()->create(['airline_id' => $fixture['airline']->id]);
    $fixture['flight']->subfleets()->attach($emptySubfleet);

    $this->actingAs($fixture['user'])
        ->getJson('/flights/'.$fixture['flight']->id.'/dispatch')
        ->assertOk()
        ->assertJsonFragment([
            'id'                    => $emptySubfleet->id,
            'eligibleAircraftCount' => 0,
            'disabled'              => true,
            'availabilityLabel'     => 'None available',
        ]);

    $this->actingAs($fixture['user'])
        ->getJson('/flights/'.$fixture['flight']->id.'/dispatch/subfleets/'.$fixture['subfleet']->id.'/aircraft')
        ->assertOk()
        ->assertJsonPath('aircraft.0.id', $fixture['aircraft']->id);
});

it('returns every authoritative dispatch policy setting', function (): void {
    updateSetting('pilots.restrict_to_company', true);
    updateSetting('pilots.only_show_flights_from_current', true);
    updateSetting('pilots.only_flights_from_current', true);
    updateSetting('pireps.restrict_aircraft_to_rank', true);
    updateSetting('pireps.restrict_aircraft_to_typerating', true);
    updateSetting('pireps.only_aircraft_at_dpt_airport', true);
    updateSetting('flights.only_company_aircraft', true);
    updateSetting('bids.disable_flight_on_bid', true);
    updateSetting('bids.allow_multiple_bids', false);
    updateSetting('bids.block_aircraft', true);
    updateSetting('bids.expire_time', 12);
    updateSetting('simbrief.api_key', 'configured');
    updateSetting('simbrief.only_bids', true);
    updateSetting('simbrief.block_aircraft', true);

    $policy = FlightDispatchPolicyData::fromSettings(true);

    expect($policy->restrictToCompany)->toBeTrue()
        ->and($policy->discoveryCurrentAirportOnly)->toBeTrue()
        ->and($policy->requireCurrentAirport)->toBeTrue()
        ->and($policy->restrictAircraftToRank)->toBeTrue()
        ->and($policy->restrictAircraftToTypeRating)->toBeTrue()
        ->and($policy->aircraftAtDepartureOnly)->toBeTrue()
        ->and($policy->companyAircraftOnly)->toBeTrue()
        ->and($policy->disableFlightOnBid)->toBeTrue()
        ->and($policy->allowMultipleBids)->toBeFalse()
        ->and($policy->pilotBidLimitReached)->toBeTrue()
        ->and($policy->aircraftRequired)->toBeTrue()
        ->and($policy->chooseLaterAllowed)->toBeFalse()
        ->and($policy->expireHours)->toBe(12)
        ->and($policy->simbriefEnabled)->toBeTrue()
        ->and($policy->simbriefRequiresBid)->toBeTrue()
        ->and($policy->simbriefBlocksAircraft)->toBeTrue();
});

it('exposes server-derived OFP URLs for the authenticated bid only', function (): void {
    $fixture = makeDispatchFixture();
    updateSetting('simbrief.api_key', 'configured');
    $bid = app(BidService::class)->addBid($fixture['flight'], $fixture['user'], $fixture['aircraft']);
    $policy = FlightDispatchPolicyData::fromSettings();

    $selection = BidSelectionData::fromModel($bid, $policy, $fixture['user']);
    expect($selection->ofpGenerated)->toBeFalse()
        ->and($selection->ofpPlanningUrl)->toBe(route('frontend.ofp.planning', ['bid_id' => $bid->id]))
        ->and($selection->ofpUrl)->toBeNull()
        ->and($selection->flight->ofpPlanningUrl)->toBe(
            route('frontend.ofp.planning').'?flight_id='.$fixture['flight']->id,
        );

    $this->actingAs($fixture['user'])
        ->get($selection->ofpPlanningUrl)
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('Ofp/Simbrief/Planning', false)
            ->where('planning.attempt.flightId', $fixture['flight']->id)
            ->where('planning.attempt.aircraftId', $fixture['aircraft']->id)
            ->where('aircraftSelection.flight.summary.id', $fixture['flight']->id)
            ->where('aircraftSelection.dispatchUrl', route('frontend.flights.dispatch', $fixture['flight']->id))
            ->has('aircraftSelection.subfleets', 1));

    $otherPilot = User::factory()->create(['airline_id' => $fixture['user']->airline_id]);
    $this->actingAs($otherPilot)
        ->get($selection->ofpPlanningUrl)
        ->assertNotFound();

    updateSetting('simbrief.api_key', '');
    $disabled = BidSelectionData::fromModel($bid, FlightDispatchPolicyData::fromSettings(), $fixture['user']);
    expect($disabled->ofpPlanningUrl)->toBeNull();
    updateSetting('simbrief.api_key', 'configured');

    $notOwned = BidSelectionData::fromModel($bid, $policy, $otherPilot);
    expect($notOwned->ofpGenerated)->toBeFalse()
        ->and($notOwned->ofpPlanningUrl)->toBeNull()
        ->and($notOwned->ofpUrl)->toBeNull();

    SimBrief::factory()->create([
        'user_id'   => $otherPilot->id,
        'flight_id' => $fixture['flight']->id,
    ]);
    $withoutOwnOfp = BidSelectionData::fromModel($bid, $policy, $fixture['user']);
    expect($withoutOwnOfp->ofpGenerated)->toBeFalse()
        ->and($withoutOwnOfp->ofpUrl)->toBeNull();

    $ofp = SimBrief::factory()->create([
        'user_id'   => $fixture['user']->id,
        'flight_id' => $fixture['flight']->id,
    ]);
    $withOwnOfp = BidSelectionData::fromModel($bid, $policy, $fixture['user']);
    expect($withOwnOfp->ofpGenerated)->toBeTrue()
        ->and($withOwnOfp->ofpPlanningUrl)->toBeNull()
        ->and($withOwnOfp->ofpUrl)->toBe(route('frontend.ofp.briefing', $ofp->id));
});

it('uses the OFP namespace only for the new Skylight route surface', function (): void {
    expect(route('frontend.ofp.planning'))->toEndWith('/ofp/planning')
        ->and(route('frontend.ofp.attempt.api-code', 'attempt-id'))->toEndWith('/ofp/attempts/attempt-id/api-code')
        ->and(route('frontend.ofp.attempt.poll', 'attempt-id'))->toEndWith('/ofp/attempts/attempt-id/poll')
        ->and(route('frontend.ofp.briefing', 'ofp-id'))->toEndWith('/ofp/briefings/ofp-id')
        ->and(route('frontend.ofp.briefing.cancel', 'ofp-id'))->toEndWith('/ofp/briefings/ofp-id')
        ->and(route('frontend.ofp.briefing.regenerate', 'ofp-id'))->toEndWith('/ofp/briefings/ofp-id/regenerate')
        ->and(route('frontend.ofp.briefing.edit-sync', 'ofp-id'))->toEndWith('/ofp/briefings/ofp-id/edit-sync')
        ->and(route('frontend.simbrief.generate'))->toEndWith('/simbrief/generate')
        ->and(route('frontend.simbrief.briefing', 'legacy-id'))->toEndWith('/simbrief/legacy-id');

    expect(Route::getRoutes()->getByName('frontend.ofp.planning')->methods())->toContain('GET')
        ->and(Route::getRoutes()->getByName('frontend.ofp.attempt.api-code')->methods())->toContain('POST')
        ->and(Route::getRoutes()->getByName('frontend.ofp.attempt.poll')->methods())->toContain('POST')
        ->and(Route::getRoutes()->getByName('frontend.ofp.briefing')->methods())->toContain('GET')
        ->and(Route::getRoutes()->getByName('frontend.ofp.briefing.cancel')->methods())->toContain('DELETE')
        ->and(Route::getRoutes()->getByName('frontend.ofp.briefing.regenerate')->methods())->toContain('POST')
        ->and(Route::getRoutes()->getByName('frontend.ofp.briefing.edit-sync')->methods())->toContain('POST')
        ->and(Route::getRoutes()->getByName('frontend.simbrief.generate')->methods())->toContain('GET')
        ->and(Route::getRoutes()->getByName('frontend.simbrief.briefing')->methods())->toContain('GET');
});

it('clamps legacy negative bid expiry and rejects new negative settings', function (): void {
    $setting = Setting::query()->where('id', 'bids_expire_time')->firstOrFail();
    $setting->update(['value' => '-3']);
    app(SettingService::class)->clearMemo();

    expect(FlightDispatchPolicyData::fromSettings()->expireHours)->toBe(0)
        ->and(fn (): mixed => app(SettingService::class)->store('bids.expire_time', -1))
        ->toThrow(ValidationException::class);
});

it('binds Skylight SimBrief attempts to their authenticated pilot without a username', function (): void {
    $fixture = makeDispatchFixture();
    updateSetting('simbrief.api_key', 'configured');
    $fixture['user']->update(['simbrief_username' => null]);

    $attempt = app(SkylightSimBriefService::class)->begin(
        $fixture['user'],
        $fixture['flight'],
        $fixture['aircraft']->id,
    );
    expect($attempt)->toBeInstanceOf(SimBriefAttempt::class);

    $otherPilot = User::factory()->create(['airline_id' => $fixture['user']->airline_id]);
    expect(fn (): SimBriefAttempt => app(SkylightSimBriefService::class)->attemptFor($otherPilot, $attempt->static_id))
        ->toThrow(ModelNotFoundException::class);
});

it('opens Skylight planning and prevents another pilot from using its static ID', function (): void {
    $fixture = makeDispatchFixture();
    updateSetting('simbrief.api_key', 'configured');
    $fixture['user']->update(['simbrief_username' => null]);

    $this->actingAs($fixture['user'])
        ->get('/ofp/planning?flight_id='.$fixture['flight']->id.'&aircraft_id='.$fixture['aircraft']->id)
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('Ofp/Simbrief/Planning', false)
            ->where('planning.attempt.flightId', $fixture['flight']->id)
            ->where('planning.attempt.aircraftId', $fixture['aircraft']->id));

    $staticId = SimBriefAttempt::query()->where('user_id', $fixture['user']->id)->value('static_id');
    $otherPilot = User::factory()->create(['airline_id' => $fixture['user']->airline_id]);

    $this->actingAs($otherPilot)
        ->postJson('/ofp/attempts/'.$staticId.'/api-code', ['apiRequest' => 'request'])
        ->assertNotFound();
});

it('returns aircraft selection for a flight-only bid before creating an OFP attempt', function (): void {
    $fixture = makeDispatchFixture();
    updateSetting('simbrief.api_key', 'configured');
    $bid = app(BidService::class)->addBid($fixture['flight'], $fixture['user']);
    $planningUrl = route('frontend.ofp.planning', ['bid_id' => $bid->id]);

    expect(BidSelectionData::fromModel($bid, FlightDispatchPolicyData::fromSettings(), $fixture['user'])->ofpPlanningUrl)
        ->toBe($planningUrl);

    $this->actingAs($fixture['user'])
        ->get($planningUrl.'&aircraft_id='.$fixture['aircraft']->id)
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('Ofp/Simbrief/Planning', false)
            ->where('planning', null)
            ->where('aircraftSelection.flight.summary.id', $fixture['flight']->id)
            ->where('aircraftSelection.flight.summary.dpt', $fixture['departure']->icao)
            ->where('aircraftSelection.flight.summary.arr', $fixture['arrival']->icao)
            ->where('aircraftSelection.flight.route', 'DCT TEST')
            ->where('aircraftSelection.dispatchUrl', route('frontend.flights.dispatch', $fixture['flight']->id))
            ->where('aircraftSelection.planningUrl', $planningUrl)
            ->where('aircraftSelection.aircraftAssignmentUrl', route('frontend.flights.bid.store', $fixture['flight']->id))
            ->has('aircraftSelection.subfleets', 1)
            ->where('aircraftSelection.subfleets.0.id', $fixture['subfleet']->id));

    expect($bid->refresh()->aircraft_id)->toBeNull()
        ->and(SimBriefAttempt::query()->where('user_id', $fixture['user']->id)->count())->toBe(0);

    $this->actingAs($fixture['user'])
        ->postJson(route('frontend.flights.bid.store', $fixture['flight']->id), [
            'aircraftId' => $fixture['aircraft']->id,
        ])
        ->assertOk()
        ->assertJsonPath('selection.aircraft.id', $fixture['aircraft']->id)
        ->assertJsonPath('selection.ofpPlanningUrl', $planningUrl);

    expect($bid->refresh()->aircraft_id)->toBe($fixture['aircraft']->id);

    $replacement = Aircraft::factory()->create([
        'subfleet_id' => $fixture['subfleet']->id,
        'airport_id'  => $fixture['departure']->id,
    ]);
    $this->actingAs($fixture['user'])
        ->postJson(route('frontend.flights.bid.store', $fixture['flight']->id), [
            'aircraftId' => $replacement->id,
        ])
        ->assertOk();
    expect($bid->refresh()->aircraft_id)->toBe($fixture['aircraft']->id);

    $this->actingAs($fixture['user'])
        ->get($planningUrl)
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('planning.attempt.flightId', $fixture['flight']->id)
            ->where('planning.attempt.aircraftId', $fixture['aircraft']->id));

    expect(SimBriefAttempt::query()->where('user_id', $fixture['user']->id)->count())->toBe(1);
});

it('rejects an ineligible aircraft when filling an unassigned bid', function (): void {
    $fixture = makeDispatchFixture();
    $bid = app(BidService::class)->addBid($fixture['flight'], $fixture['user']);
    $inactive = Aircraft::factory()->create([
        'subfleet_id' => $fixture['subfleet']->id,
        'airport_id'  => $fixture['departure']->id,
        'status'      => AircraftStatus::MAINTENANCE,
    ]);

    $this->actingAs($fixture['user'])
        ->postJson(route('frontend.flights.bid.store', $fixture['flight']->id), [
            'aircraftId' => $inactive->id,
        ])
        ->assertUnprocessable()
        ->assertJsonPath('type', 'stale-aircraft');

    expect($bid->refresh()->aircraft_id)->toBeNull();
});

it('uses the configured cargo load factor for Skylight SimBrief planning', function (): void {
    $fixture = makeDispatchFixture();
    updateSetting('simbrief.api_key', 'configured');
    updateSetting('flights.default_load_factor', 10);
    updateSetting('flights.load_factor_variance', 0);
    updateSetting('flights.default_cargo_load_factor', 100);
    updateSetting('flights.cargo_load_factor_variance', 0);
    updateSetting('flights.use_cargo_load_factor', true);
    $fixture['flight']->update(['load_factor' => null, 'load_factor_variance' => null]);
    $fixture['flight']->fares()->attach(Fare::factory()->create([
        'type'     => FareType::PASSENGER,
        'capacity' => 10,
    ]));
    $fixture['flight']->fares()->attach(Fare::factory()->create([
        'type'     => FareType::CARGO,
        'capacity' => 1000,
    ]));

    $this->actingAs($fixture['user'])
        ->get('/ofp/planning?flight_id='.$fixture['flight']->id.'&aircraft_id='.$fixture['aircraft']->id)
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('Ofp/Simbrief/Planning', false)
            ->where('planning.providerFields.cargo', '1.0'));
});

it('provides the same editable callsign choices as Seven', function (): void {
    $fixture = makeDispatchFixture();
    updateSetting('simbrief.api_key', 'configured');
    updateSetting('simbrief.callsign', false);
    $fixture['flight']->update(['callsign' => 'ALPHA']);
    $fixture['user']->update(['callsign' => 'PILOT']);
    $airlineIcao = $fixture['airline']->icao;

    $this->actingAs($fixture['user'])
        ->get('/ofp/planning?flight_id='.$fixture['flight']->id.'&aircraft_id='.$fixture['aircraft']->id)
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('Ofp/Simbrief/Planning', false)
            ->where('planning.callsignEditable', true)
            ->where('planning.callsignOptions', [
                $airlineIcao.'ALPHA',
                $airlineIcao.$fixture['flight']->flight_number,
                $airlineIcao.'PILOT',
                $fixture['user']->ident,
            ]));
});

it('keeps the pilot ident fixed when the Seven callsign setting is enabled', function (): void {
    $fixture = makeDispatchFixture();
    updateSetting('simbrief.api_key', 'configured');
    updateSetting('simbrief.callsign', true);

    $this->actingAs($fixture['user'])
        ->get('/ofp/planning?flight_id='.$fixture['flight']->id.'&aircraft_id='.$fixture['aircraft']->id)
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('Ofp/Simbrief/Planning', false)
            ->where('planning.callsignEditable', false)
            ->where('planning.callsignOptions', [$fixture['user']->ident])
            ->where('planning.providerFields.callsign', $fixture['user']->ident));
});

it('stores an optional aircraft preference or a flight-only bid when blocking is off', function (): void {
    $fixture = makeDispatchFixture();
    $service = app(BidService::class);

    $preferred = $service->addBid($fixture['flight'], $fixture['user'], $fixture['aircraft']);
    expect($preferred->aircraft_id)->toBe($fixture['aircraft']->id);

    $service->removeBid($fixture['flight'], $fixture['user']);
    $flightOnly = $service->addBid($fixture['flight'], $fixture['user']);
    expect($flightOnly->aircraft_id)->toBeNull();
});

it('requires an eligible aircraft when blocking is on', function (): void {
    updateSetting('bids.block_aircraft', true);
    $fixture = makeDispatchFixture();

    expect(fn () => app(BidService::class)->addBid($fixture['flight'], $fixture['user']))
        ->toThrow(ValidationException::class);
});

it('returns the same bid for an idempotent repeat submission', function (): void {
    $fixture = makeDispatchFixture();
    $service = app(BidService::class);

    $first = $service->addBid($fixture['flight'], $fixture['user']);
    $second = $service->addBid($fixture['flight'], $fixture['user']);

    expect($second->id)->toBe($first->id)
        ->and(Bid::query()->where('user_id', $fixture['user']->id)->where('flight_id', $fixture['flight']->id)->count())->toBe(1);
});

it('returns typed pilot, flight, and aircraft conflicts', function (): void {
    $first = makeDispatchFixture();
    $secondFlight = makeDispatchFixture($first['user']);

    updateSetting('bids.allow_multiple_bids', false);
    app(BidService::class)->addBid($first['flight'], $first['user']);
    $this->actingAs($first['user'])
        ->postJson('/flights/'.$secondFlight['flight']->id.'/bid')
        ->assertConflict()
        ->assertJsonPath('type', 'pilot-limit');

    updateSetting('bids.allow_multiple_bids', true);
    updateSetting('bids.disable_flight_on_bid', true);
    $otherPilot = User::factory()->create(['airline_id' => $first['user']->airline_id]);
    $this->actingAs($otherPilot)
        ->postJson('/flights/'.$first['flight']->id.'/bid')
        ->assertConflict()
        ->assertJsonPath('type', 'flight-conflict');

    updateSetting('bids.disable_flight_on_bid', false);
    updateSetting('bids.block_aircraft', true);
    $reserved = makeDispatchFixture();
    app(BidService::class)->addBid($reserved['flight'], $reserved['user'], $reserved['aircraft']);
    $thirdPilot = User::factory()->create(['airline_id' => $first['user']->airline_id]);
    $thirdFlight = makeDispatchFixture($thirdPilot);
    $this->actingAs($thirdPilot)
        ->postJson('/flights/'.$thirdFlight['flight']->id.'/bid', ['aircraftId' => $reserved['aircraft']->id])
        ->assertConflict()
        ->assertJsonPath('type', 'aircraft-conflict');
});

it('returns a typed stale response when the selected aircraft disappeared', function (): void {
    $fixture = makeDispatchFixture();

    $this->actingAs($fixture['user'])
        ->postJson('/flights/'.$fixture['flight']->id.'/bid', ['aircraftId' => 999999])
        ->assertUnprocessable()
        ->assertJsonPath('type', 'stale-aircraft')
        ->assertJsonPath('errors.aircraftId.0', 'This aircraft is no longer available.');
});

it('returns a typed validation response for malformed selection input', function (): void {
    $fixture = makeDispatchFixture();

    $this->actingAs($fixture['user'])
        ->postJson('/flights/'.$fixture['flight']->id.'/bid', ['aircraftId' => 'not-an-id'])
        ->assertUnprocessable()
        ->assertJsonPath('type', 'validation')
        ->assertJsonPath('errors.aircraftId.0', 'The aircraft id must be an integer.');
});

it('filters aircraft by active parked airport reservation and SimBrief rules', function (): void {
    $fixture = makeDispatchFixture();
    updateSetting('pireps.only_aircraft_at_dpt_airport', true);
    updateSetting('bids.block_aircraft', true);
    updateSetting('simbrief.block_aircraft', true);

    Aircraft::factory()->create([
        'subfleet_id' => $fixture['subfleet']->id,
        'airport_id'  => $fixture['departure']->id,
        'status'      => AircraftStatus::MAINTENANCE,
    ]);
    Aircraft::factory()->create([
        'subfleet_id' => $fixture['subfleet']->id,
        'airport_id'  => $fixture['departure']->id,
        'state'       => AircraftState::IN_USE,
    ]);
    $simbriefAircraft = Aircraft::factory()->create([
        'subfleet_id' => $fixture['subfleet']->id,
        'airport_id'  => $fixture['departure']->id,
    ]);
    SimBrief::factory()->create([
        'user_id'     => $fixture['user']->id,
        'flight_id'   => $fixture['flight']->id,
        'aircraft_id' => $simbriefAircraft->id,
    ]);

    $ids = app(BidService::class)->eligibleAircraftQuery($fixture['flight'], $fixture['user'])->pluck('id');
    expect($ids->all())->toBe([$fixture['aircraft']->id]);
});

it('rechecks company and operational current-airport rules at mutation time', function (): void {
    $fixture = makeDispatchFixture();
    updateSetting('pilots.only_flights_from_current', true);
    $fixture['user']->update(['curr_airport_id' => Airport::factory()->create()->id]);

    expect(fn () => app(BidService::class)->addBid($fixture['flight'], $fixture['user']))
        ->toThrow(ValidationException::class);

    updateSetting('pilots.only_flights_from_current', false);
    updateSetting('pilots.restrict_to_company', true);
    $fixture['flight']->update(['airline_id' => Airline::factory()->create()->id]);

    expect(fn () => app(BidService::class)->addBid($fixture['flight'], $fixture['user']))
        ->toThrow(ValidationException::class);
});

it('recomputes flight state and removes unused SimBrief data during expiry cleanup', function (): void {
    $fixture = makeDispatchFixture();
    updateSetting('simbrief.only_bids', true);
    updateSetting('bids.expire_time', 1);
    $bid = app(BidService::class)->addBid($fixture['flight'], $fixture['user']);
    Bid::query()->whereKey($bid->id)->update(['created_at' => Carbon::now('UTC')->subHours(2)]);
    $simbrief = SimBrief::factory()->create([
        'user_id'   => $fixture['user']->id,
        'flight_id' => $fixture['flight']->id,
    ]);

    app(RemoveExpiredBids::class)->handle(new CronHourly());

    expect(Bid::query()->find($bid->id))->toBeNull()
        ->and(SimBrief::query()->find($simbrief->id))->toBeNull()
        ->and($fixture['flight']->refresh()->has_bid)->toBeFalse();
});

it('routes flight user and PIREP cleanup through shared bid removal', function (): void {
    updateSetting('simbrief.only_bids', true);

    $flightFixture = makeDispatchFixture();
    $flightBid = app(BidService::class)->addBid($flightFixture['flight'], $flightFixture['user']);
    $flightBrief = SimBrief::factory()->create([
        'user_id'   => $flightFixture['user']->id,
        'flight_id' => $flightFixture['flight']->id,
    ]);
    app(FlightService::class)->deleteFlight($flightFixture['flight']);
    expect(Bid::query()->find($flightBid->id))->toBeNull()
        ->and(SimBrief::query()->find($flightBrief->id))->toBeNull()
        ->and(Flight::withTrashed()->findOrFail($flightFixture['flight']->id)->has_bid)->toBeFalse();

    $userFixture = makeDispatchFixture();
    app(BidService::class)->addBid($userFixture['flight'], $userFixture['user']);
    app(UserService::class)->removeUser($userFixture['user']);
    expect(Bid::query()->where('user_id', $userFixture['user']->id)->exists())->toBeFalse()
        ->and($userFixture['flight']->refresh()->has_bid)->toBeFalse();

    $pirepFixture = makeDispatchFixture();
    app(BidService::class)->addBid($pirepFixture['flight'], $pirepFixture['user']);
    $pirep = Pirep::factory()->create([
        'user_id'   => $pirepFixture['user']->id,
        'flight_id' => $pirepFixture['flight']->id,
    ]);
    app(BidService::class)->removeBidForPirep($pirep);
    expect(Bid::query()->where('user_id', $pirepFixture['user']->id)->where('flight_id', $pirepFixture['flight']->id)->exists())->toBeFalse()
        ->and($pirepFixture['flight']->refresh()->has_bid)->toBeFalse();
});

it('ignores target-user input on the self-only Inertia mutation', function (): void {
    $fixture = makeDispatchFixture();
    $target = User::factory()->create();

    $this->actingAs($fixture['user'])
        ->postJson('/flights/'.$fixture['flight']->id.'/bid', ['userId' => $target->id])
        ->assertOk();

    expect(Bid::query()->where('user_id', $fixture['user']->id)->where('flight_id', $fixture['flight']->id)->exists())->toBeTrue()
        ->and(Bid::query()->where('user_id', $target->id)->exists())->toBeFalse();
});

it('removes only the authenticated pilot bid through the non-GET Skylight route', function (): void {
    $fixture = makeDispatchFixture();
    $otherPilot = User::factory()->create(['airline_id' => $fixture['user']->airline_id]);
    $otherBid = Bid::factory()->create([
        'user_id'   => $otherPilot->id,
        'flight_id' => $fixture['flight']->id,
    ]);
    $ownBid = Bid::factory()->create([
        'user_id'   => $fixture['user']->id,
        'flight_id' => $fixture['flight']->id,
    ]);

    $this->actingAs($fixture['user'])
        ->deleteJson('/flights/'.$fixture['flight']->id.'/bid')
        ->assertOk()
        ->assertJsonPath('bidsUrl', route('frontend.flights.bids'));

    expect(Bid::query()->find($ownBid->id))->toBeNull()
        ->and(Bid::query()->find($otherBid->id))->not->toBeNull();
});

it('uses non-GET owner-scoped SimBrief lifecycle mutations', function (): void {
    $fixture = makeDispatchFixture();
    $briefing = SimBrief::factory()->create([
        'user_id'     => $fixture['user']->id,
        'flight_id'   => $fixture['flight']->id,
        'aircraft_id' => $fixture['aircraft']->id,
        'static_id'   => 'ABCD1234EFGH5678IJKL9012',
    ]);
    $otherPilot = User::factory()->create(['airline_id' => $fixture['user']->airline_id]);

    $this->actingAs($otherPilot)
        ->postJson('/ofp/briefings/'.$briefing->id.'/regenerate')
        ->assertNotFound();

    $this->actingAs($otherPilot)
        ->postJson('/ofp/briefings/'.$briefing->id.'/edit-sync')
        ->assertNotFound();

    $this->actingAs($fixture['user'])
        ->postJson('/ofp/briefings/'.$briefing->id.'/regenerate')
        ->assertOk()
        ->assertJsonPath(
            'planningUrl',
            route('frontend.ofp.planning', [
                'flight_id'   => $fixture['flight']->id,
                'aircraft_id' => $fixture['aircraft']->id,
            ]),
        );

    expect(SimBrief::query()->find($briefing->id))->toBeNull();
});

it('characterizes the legacy target-user API route and explicit input target behavior', function (): void {
    $actor = User::factory()->create();
    $target = User::factory()->create();
    $flight = Flight::factory()->create(['airline_id' => $target->airline_id]);
    apiAs($actor);

    $this->putJson('/api/users/'.$target->id.'/bids', ['flight_id' => $flight->id])
        ->assertOk()
        ->assertJsonPath('data.user_id', $actor->id);

    $this->putJson('/api/users/'.$target->id.'/bids', [
        'id'        => $target->id,
        'flight_id' => $flight->id,
    ])->assertOk()->assertJsonPath('data.user_id', $target->id);
});

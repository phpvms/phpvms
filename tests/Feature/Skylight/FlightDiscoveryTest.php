<?php

declare(strict_types=1);

use App\Models\Airline;
use App\Models\Airport;
use App\Models\Bid;
use App\Models\Flight;
use App\Models\Rank;
use App\Models\Subfleet;
use App\Models\User;
use Igaster\LaravelTheme\Facades\Theme;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    Theme::set('skylight');
    updateSetting('general.theme', 'skylight');
    updateSetting('pilots.restrict_to_company', false);
    updateSetting('pilots.only_show_flights_from_current', false);
    updateSetting('pilots.only_flights_from_current', false);
    updateSetting('pireps.restrict_aircraft_to_rank', false);
    updateSetting('pireps.restrict_aircraft_to_typerating', false);
    updateSetting('bids.allow_multiple_bids', true);
    updateSetting('bids.disable_flight_on_bid', false);
});

it('preserves the complete Seven-compatible filter state in the typed flight response', function (): void {
    $user = User::factory()->create();
    $query = [
        'airline_id'     => (string) $user->airline_id,
        'flight_number'  => '104',
        'flight_type'    => 'J',
        'route_code'     => 'A',
        'dep_icao'       => 'KDFW',
        'arr_icao'       => 'KORD',
        'dgt'            => '500',
        'dlt'            => '900',
        'tgt'            => '60',
        'tlt'            => '180',
        'subfleet_id'    => '4',
        'type_rating_id' => '5',
        'icao_type'      => 'A320',
        'search'         => 'morning',
        'orderBy'        => 'dpt_time',
        'sortedBy'       => 'asc',
        'limit'          => '50',
    ];

    $this->actingAs($user)
        ->get('/flights?'.http_build_query($query))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('Flights', false)
            ->where('filters.airlineId', $query['airline_id'])
            ->where('filters.flightNumber', '104')
            ->where('filters.flightType', 'J')
            ->where('filters.routeCode', 'A')
            ->where('filters.depIcao', 'KDFW')
            ->where('filters.arrIcao', 'KORD')
            ->where('filters.distanceGreaterThan', '500')
            ->where('filters.distanceLessThan', '900')
            ->where('filters.timeGreaterThan', '60')
            ->where('filters.timeLessThan', '180')
            ->where('filters.subfleetId', '4')
            ->where('filters.typeRatingId', '5')
            ->where('filters.icaoType', 'A320')
            ->where('filters.search', 'morning')
            ->where('filters.orderBy', 'dpt_time')
            ->where('filters.sortedBy', 'asc')
            ->where('filters.limit', '50'));
});

it('keeps discovery visibility separate from operational current-airport availability', function (): void {
    $current = Airport::factory()->create(['id' => 'KDFW']);
    $departure = Airport::factory()->create(['id' => 'KORD']);
    $user = User::factory()->create(['curr_airport_id' => $current->id]);
    $flight = Flight::factory()->create([
        'airline_id'     => $user->airline_id,
        'dpt_airport_id' => $departure->id,
    ]);
    updateSetting('pilots.only_show_flights_from_current', false);
    updateSetting('pilots.only_flights_from_current', true);

    $this->actingAs($user)
        ->get('/flights')
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('flights.0.id', $flight->id)
            ->where('flights.0.availability', 'locked')
            ->where('flights.0.primaryAction', 'unavailable')
            ->where('policy.discoveryCurrentAirportOnly', false)
            ->where('policy.requireCurrentAirport', true));

    updateSetting('pilots.only_show_flights_from_current', true);
    $this->actingAs($user)
        ->get('/flights')
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page->has('flights', 0));
});

it('renders typed Skylight flight detail and preserves the legacy Blade route', function (): void {
    $user = User::factory()->create();
    $departure = Airport::factory()->create();
    $arrival = Airport::factory()->create();
    $flight = Flight::factory()->create([
        'airline_id'     => $user->airline_id,
        'dpt_airport_id' => $departure->id,
        'arr_airport_id' => $arrival->id,
    ]);

    $this->actingAs($user)
        ->get('/flights/'.$flight->id)
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('Flights/Show', false)
            ->where('flight.summary.id', $flight->id)
            ->where('flight.departureWeather.icao', $departure->icao)
            ->where('flight.arrivalWeather.icao', $arrival->icao));

    Theme::set('seven');
    updateSetting('general.theme', 'seven');
    $this->actingAs($user)
        ->get('/flights/'.$flight->id)
        ->assertOk()
        ->assertViewHasAll(['flight', 'map_features', 'bid', 'acars_plugin']);
});

it('does not disclose missing or company-inaccessible flight details', function (): void {
    $user = User::factory()->create();
    $otherAirline = Airline::factory()->create();
    $flight = Flight::factory()->create(['airline_id' => $otherAirline->id]);
    updateSetting('pilots.restrict_to_company', true);

    $this->actingAs($user)
        ->get('/flights/missing-flight')
        ->assertRedirect(route('frontend.dashboard.index'));
    $this->actingAs($user)
        ->get('/flights/'.$flight->id)
        ->assertRedirect(route('frontend.dashboard.index'));
});

it('does not disclose hidden inactive-airline rank or type-rating inaccessible details', function (): void {
    $user = User::factory()->create(['rank_id' => Rank::factory()->create()->id]);
    $subfleet = Subfleet::factory()->create(['airline_id' => $user->airline_id]);
    $hidden = Flight::factory()->create(['airline_id' => $user->airline_id, 'visible' => false]);
    $inactiveAirline = Airline::factory()->create(['active' => false]);
    $inactiveAirlineFlight = Flight::factory()->create(['airline_id' => $inactiveAirline->id]);
    $restricted = Flight::factory()->hasAttached($subfleet)->create(['airline_id' => $user->airline_id]);

    $this->actingAs($user)->get('/flights/'.$hidden->id)->assertRedirect(route('frontend.dashboard.index'));
    $this->actingAs($user)->get('/flights/'.$inactiveAirlineFlight->id)->assertRedirect(route('frontend.dashboard.index'));

    updateSetting('pireps.restrict_aircraft_to_rank', true);
    $this->actingAs($user)->get('/flights/'.$restricted->id)->assertOk();

    updateSetting('pireps.restrict_aircraft_to_rank', false);
    updateSetting('pireps.restrict_aircraft_to_typerating', true);
    $this->actingAs($user)->get('/flights/'.$restricted->id)->assertOk();
});

it('derives exclusive flight and pilot-limit actions without exposing model payloads', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create(['airline_id' => $user->airline_id]);
    $flight = Flight::factory()->create(['airline_id' => $user->airline_id, 'has_bid' => true]);
    Bid::factory()->create(['user_id' => $other->id, 'flight_id' => $flight->id]);
    updateSetting('bids.disable_flight_on_bid', true);

    $this->actingAs($user)
        ->get('/flights')
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('flights.0.id', $flight->id)
            ->where('flights.0.availabilityReason', 'Another pilot has a bid on this flight')
            ->where('flights.0.primaryAction', 'unavailable')
            ->missing('flights.0.created_at')
            ->missing('flights.0.deleted_at'));
});

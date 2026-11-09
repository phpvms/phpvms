<?php

declare(strict_types=1);

require_once __DIR__.'/../Support.php';

use App\Models\Aircraft;
use App\Models\Airline;
use App\Models\Airport;
use App\Models\Flight;
use App\Models\SimBrief;
use App\Models\Subfleet;
use App\Models\User;

/**
 * A pilot, a flight with one subfleet and one parked aircraft at its departure
 * airport, with SimBrief configured. Airside is the active theme.
 *
 * @return array{user: User, airline: Airline, subfleet: Subfleet, aircraft: Aircraft, flight: Flight, departure: Airport, arrival: Airport}
 */
function airsideOfpFixture(): array
{
    useAirside();
    updateSetting('simbrief.api_key', 'configured');
    updateSetting('simbrief.only_bids', false);
    updateSetting('simbrief.block_aircraft', false);
    updateSetting('bids.allow_multiple_bids', true);
    updateSetting('bids.block_aircraft', false);
    updateSetting('bids.disable_flight_on_bid', false);
    updateSetting('bids.expire_time', 0);
    updateSetting('pilots.restrict_to_company', false);
    updateSetting('operations.only_flights_from_current', false);
    updateSetting('operations.only_show_flights_from_current', false);
    updateSetting('pireps.restrict_aircraft_to_rank', false);
    updateSetting('pireps.restrict_aircraft_to_typerating', false);
    updateSetting('pireps.only_aircraft_at_dpt_airport', false);
    updateSetting('flights.only_company_aircraft', false);

    $departure = Airport::factory()->create();
    $arrival = Airport::factory()->create();
    $user = User::factory()->create(['curr_airport_id' => $departure->id]);
    $airline = Airline::query()->findOrFail($user->airline_id);
    $subfleet = Subfleet::factory()->create(['airline_id' => $airline->id]);
    $aircraft = Aircraft::factory()->create(['subfleet_id' => $subfleet->id, 'airport_id' => $departure->id]);
    $flight = Flight::factory()->hasAttached($subfleet)->create([
        'airline_id'     => $airline->id,
        'dpt_airport_id' => $departure->id,
        'arr_airport_id' => $arrival->id,
        'route'          => 'DCT TEST',
    ]);

    return ['user' => $user, 'airline' => $airline, 'subfleet' => $subfleet, 'aircraft' => $aircraft, 'flight' => $flight, 'departure' => $departure, 'arrival' => $arrival];
}

/**
 * A completed SimBrief briefing for the pilot's flight and aircraft.
 *
 * @param array{user: User, aircraft: Aircraft, flight: Flight} $fixture
 * @param array<string, mixed>                                  $attributes
 */
function airsideBriefing(array $fixture, array $attributes = []): SimBrief
{
    return SimBrief::factory()->create([
        'user_id'     => $fixture['user']->id,
        'flight_id'   => $fixture['flight']->id,
        'aircraft_id' => $fixture['aircraft']->id,
        'static_id'   => 'static-ofp-1',
        ...$attributes,
    ]);
}

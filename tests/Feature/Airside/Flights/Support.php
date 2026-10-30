<?php

declare(strict_types=1);

use App\Models\Aircraft;
use App\Models\Airline;
use App\Models\Airport;
use App\Models\Flight;
use App\Models\Subfleet;
use App\Models\User;

require_once __DIR__.'/../Support.php';

/**
 * Shared set-up for the airside flights and bids tests: airside is active, the
 * dispatch settings are neutral, and a pilot stands at an airport with a flight,
 * a subfleet and a parked aircraft.
 */
function airsideFlightsBaseline(): void
{
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
}

/**
 * @return array{user: User, airline: Airline, subfleet: Subfleet, aircraft: Aircraft, flight: Flight, departure: Airport, arrival: Airport}
 */
function airsideDispatchFixture(?User $user = null, array $flight = []): array
{
    $departure = Airport::factory()->create();
    $arrival = Airport::factory()->create();
    $user ??= User::factory()->create(['curr_airport_id' => $departure->id]);
    $airline = Airline::query()->findOrFail($user->airline_id);
    $subfleet = Subfleet::factory()->create(['airline_id' => $airline->id]);
    $aircraft = Aircraft::factory()->create(['subfleet_id' => $subfleet->id, 'airport_id' => $departure->id]);
    $flight = Flight::factory()->hasAttached($subfleet)->create([
        'airline_id'     => $airline->id,
        'dpt_airport_id' => $departure->id,
        'arr_airport_id' => $arrival->id,
        'route'          => 'DCT TEST',
        ...$flight,
    ]);

    return ['user' => $user, 'airline' => $airline, 'subfleet' => $subfleet, 'aircraft' => $aircraft, 'flight' => $flight, 'departure' => $departure, 'arrival' => $arrival];
}

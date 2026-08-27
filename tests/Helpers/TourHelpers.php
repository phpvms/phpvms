<?php

declare(strict_types=1);

use App\Enums\BundleType;
use App\Enums\PirepPhase;
use App\Enums\PirepState;
use App\Models\Aircraft;
use App\Models\Airline;
use App\Models\Airport;
use App\Models\Flight;
use App\Models\FlightBundle;
use App\Models\Pirep;
use App\Models\Subfleet;
use App\Models\User;
use App\Services\PirepService;
use Illuminate\Support\Collection;

/**
 * Turn off every bid guard the tour tests are not exercising, so a test that
 * flips one setting on is the only one that setting is true for.
 */
function tourSettingsBaseline(): void
{
    updateSetting('bids.allow_multiple_bids', true);
    updateSetting('bids.disable_flight_on_bid', false);
    updateSetting('bids.block_aircraft', false);
    updateSetting('bids.expire_time', 0);
    updateSetting('pilots.restrict_to_company', false);
    updateSetting('pilots.only_flights_from_current', false);
    updateSetting('pireps.restrict_aircraft_to_rank', false);
    updateSetting('pireps.restrict_aircraft_to_typerating', false);
    updateSetting('pireps.only_aircraft_at_dpt_airport', false);
    updateSetting('pireps.handle_diversion', false);
    updateSetting('flights.only_company_aircraft', false);
    updateSetting('simbrief.block_aircraft', false);
    updateSetting('simbrief.only_bids', false);
    updateSetting('tours.max_in_progress', 0);
}

/**
 * A chained tour: `$legCount` flights numbered 1..N over N+1 airports, each leg
 * departing where the previous one arrived, plus a pilot standing at leg 1's
 * departure airport with an aircraft parked there.
 *
 * @return array{
 *     bundle: FlightBundle,
 *     flights: Collection<int, Flight>,
 *     user: User,
 *     aircraft: Aircraft,
 *     subfleet: Subfleet,
 *     airports: Collection<int, Airport>,
 * }
 */
function makeTour(int $legCount = 5, ?User $user = null, ?FlightBundle $bundle = null): array
{
    $airports = Airport::factory()->count($legCount + 1)->create();
    $user ??= User::factory()->create();
    $user->curr_airport_id = $airports[0]->id;
    $user->save();

    $airline = Airline::query()->findOrFail($user->airline_id);
    $subfleet = Subfleet::factory()->create(['airline_id' => $airline->id]);
    $aircraft = Aircraft::factory()->create([
        'subfleet_id' => $subfleet->id,
        'airport_id'  => $airports[0]->id,
    ]);

    $bundle ??= FlightBundle::factory()->create(['type' => BundleType::Tour]);

    $flights = collect(range(1, $legCount))->map(
        fn (int $leg): Flight => Flight::factory()->hasAttached($subfleet)->create([
            'airline_id'     => $airline->id,
            'bundle_id'      => $bundle->id,
            'route_leg'      => $leg,
            'dpt_airport_id' => $airports[$leg - 1]->id,
            'arr_airport_id' => $airports[$leg]->id,
        ]),
    );

    return [
        'bundle'   => $bundle,
        'flights'  => $flights,
        'user'     => $user,
        'aircraft' => $aircraft,
        'subfleet' => $subfleet,
        'airports' => $airports,
    ];
}

/**
 * File a PIREP for one leg the way the frontend does — create() then submit() —
 * so PirepFiled fires and the tour advances through its real listener.
 */
function fileTourLeg(User $user, Flight $flight, Aircraft $aircraft): Pirep
{
    $pirep = Pirep::factory()->create([
        'user_id'        => $user->id,
        'flight_id'      => $flight->id,
        'airline_id'     => $flight->airline_id,
        'aircraft_id'    => $aircraft->id,
        'dpt_airport_id' => $flight->dpt_airport_id,
        'arr_airport_id' => $flight->arr_airport_id,
        'route_leg'      => $flight->route_leg,
        'state'          => PirepState::PENDING,
        'status'         => PirepPhase::ARRIVED,
    ]);

    app(PirepService::class)->submit($pirep);

    return $pirep->refresh();
}

<?php

declare(strict_types=1);

use App\Models\Aircraft;
use App\Models\Airline;
use App\Models\Airport;
use App\Models\Fare;
use App\Models\Subfleet;
use App\Models\User;
use App\Services\FareService;

require_once __DIR__.'/../Support.php';

/**
 * Airside is active and the filing rules are neutral, then a pilot, two airports and two
 * subfleets whose fares overlap: A has fares 1 and 2, B has fares 2 and 3.
 *
 * @return array{pilot: User, airline: Airline, dpt: Airport, arr: Airport, subfleetA: Subfleet, aircraftA: Aircraft, subfleetB: Subfleet, aircraftB: Aircraft, fares: array<int, Fare>}
 */
function pirepFormFixture(): array
{
    $pilot = useAirside();

    foreach (['pilots.only_flights_from_current', 'pireps.restrict_aircraft_to_rank', 'pireps.only_aircraft_at_dpt_airport', 'pireps.disable_manual'] as $setting) {
        updateSetting($setting, false);
    }

    $dpt = Airport::factory()->create();
    $arr = Airport::factory()->create();
    $airline = Airline::query()->findOrFail($pilot->airline_id);
    $pilot->update(['curr_airport_id' => $dpt->id]);

    $subfleetA = Subfleet::factory()->create(['airline_id' => $airline->id, 'type' => 'A320', 'name' => 'Airbus A320']);
    $subfleetB = Subfleet::factory()->create(['airline_id' => $airline->id, 'type' => 'B738', 'name' => 'Boeing 737']);
    $aircraftA = Aircraft::factory()->create(['subfleet_id' => $subfleetA->id, 'airport_id' => $dpt->id, 'registration' => 'N-AAA']);
    $aircraftB = Aircraft::factory()->create(['subfleet_id' => $subfleetB->id, 'airport_id' => $dpt->id, 'registration' => 'N-BBB']);

    $fares = [
        1 => Fare::factory()->create(['name' => 'Fare Alpha', 'code' => 'Y']),
        2 => Fare::factory()->create(['name' => 'Fare Bravo', 'code' => 'J']),
        3 => Fare::factory()->create(['name' => 'Fare Charlie', 'code' => 'C']),
    ];

    $fareSvc = app(FareService::class);
    $fareSvc->setForSubfleet($subfleetA, $fares[1]);
    $fareSvc->setForSubfleet($subfleetA, $fares[2]);
    $fareSvc->setForSubfleet($subfleetB, $fares[2]);
    $fareSvc->setForSubfleet($subfleetB, $fares[3]);

    return ['pilot' => $pilot, 'airline' => $airline, 'dpt' => $dpt, 'arr' => $arr, 'subfleetA' => $subfleetA, 'aircraftA' => $aircraftA, 'subfleetB' => $subfleetB, 'aircraftB' => $aircraftB, 'fares' => $fares];
}

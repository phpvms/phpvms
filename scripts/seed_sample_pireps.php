<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Sample PIREP Seeder
|--------------------------------------------------------------------------
|
| Inserts sample airports, subfleets, aircraft, and 10 accepted PIREPs
| owned by the admin user (id=1) on airline_id=1. Useful for populating
| an empty dev database with realistic-looking PIREP data.
|
| Usage:
|   docker exec phpvms-laravel.test-1 php scripts/seed_sample_pireps.php
|   vendor/bin/sail exec laravel.test php scripts/seed_sample_pireps.php
|
| Prerequisites:
|   - Database migrated
|   - Admin user (id=1) exists
|   - Airline (id=1) exists
|
| Airports inserted: KLGA, KPAE, KSEA, KSAN, KLAX, EGLL, MKJP, OMDB
| (combined with KAUS + KJFK from base seed data → 10 total).
|
| Subfleets inserted: 4 (B744, B772-ER, B772-LR, A320) from sample.yml.
| Aircraft inserted: 3 active (B744 N001Z @ KJFK, B744 S2333 @ KAUS,
| A320 N786DL @ EGLL). Each seeded PIREP gets aircraft assigned
| round-robin.
|
*/

use App\Enums\PirepState;
use App\Enums\PirepStatus;
use App\Models\Aircraft;
use App\Models\Airport;
use App\Models\Pirep;
use App\Models\Subfleet;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

$root = dirname(__DIR__);
require $root.'/vendor/autoload.php';
$app = require_once $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$airports = [
    ['id' => 'KLGA', 'iata' => 'LGA', 'icao' => 'KLGA', 'name' => 'La Guardia Airport',                     'location' => 'New York, New York, USA', 'country' => 'United States',         'timezone' => 'America/New_York',     'lat' => 40.7772, 'lon' => -73.8726, 'hub' => 1, 'ground_handling_cost' => 250],
    ['id' => 'KPAE', 'iata' => 'PAE', 'icao' => 'KPAE', 'name' => 'Snohomish County (Paine Field) Airport', 'location' => 'Everett',                 'country' => 'United States',         'timezone' => 'America/Los_Angeles',  'lat' => 47.9063, 'lon' => -122.282, 'hub' => 0],
    ['id' => 'KSEA', 'iata' => 'SEA', 'icao' => 'KSEA', 'name' => 'Seattle Tacoma International Airport',   'location' => 'Seattle',                 'country' => 'United States',         'timezone' => 'America/Los_Angeles',  'lat' => 47.449,  'lon' => -122.309, 'hub' => 0],
    ['id' => 'KSAN', 'iata' => 'SAN', 'icao' => 'KSAN', 'name' => 'San Diego International Airport',       'location' => 'San Diego',               'country' => 'United States',         'timezone' => 'America/Los_Angeles',  'lat' => 32.7336, 'lon' => -117.19,  'hub' => 0],
    ['id' => 'KLAX', 'iata' => 'LAX', 'icao' => 'KLAX', 'name' => 'Los Angeles International Airport',     'location' => 'Los Angeles',             'country' => 'United States',         'timezone' => 'America/Los_Angeles',  'lat' => 33.9425, 'lon' => -118.408, 'hub' => 1],
    ['id' => 'EGLL', 'iata' => 'LHR', 'icao' => 'EGLL', 'name' => 'London Heathrow',                       'location' => 'London, England',         'country' => 'United Kingdom',        'timezone' => 'Europe/London',        'lat' => 51.4775, 'lon' => -0.4614,  'hub' => 0, 'ground_handling_cost' => 500],
    ['id' => 'MKJP', 'iata' => 'KIN', 'icao' => 'MKJP', 'name' => 'Norman Manley International Airport',   'location' => 'Kingston, Jamaica',       'country' => 'Jamaica',               'timezone' => 'America/Jamaica',      'lat' => 17.9357, 'lon' => -76.7875, 'hub' => 0, 'ground_handling_cost' => 50],
    ['id' => 'OMDB', 'iata' => 'DXB', 'icao' => 'OMDB', 'name' => 'Dubai International Airport',           'location' => 'Dubai, UAE',              'country' => 'United Arab Emirates',  'timezone' => 'Asia/Dubai',           'lat' => 25.2528, 'lon' => 55.3644,  'hub' => 0, 'ground_handling_cost' => 50],
];

foreach ($airports as $airport) {
    Airport::updateOrCreate(['id' => $airport['id']], $airport);
}

echo 'Airports total: '.Airport::withoutGlobalScopes()->count().PHP_EOL;

// Subfleet.id is auto_increment + not fillable; lookup by (airline_id, type) for idempotency.
$subfleetSeed = [
    ['airline_id' => 1, 'type' => '744-3X-RB211',      'name' => '747-43X RB211-524G',    'cost_block_hour' => 1000.00, 'cost_delay_minute' => 0.00, 'ground_handling_multiplier' => 200.00],
    ['airline_id' => 1, 'type' => '772-22ER-GE90-76B', 'name' => '777-222ER GE90-76B',    'cost_block_hour' => 500.00,  'cost_delay_minute' => 0.00, 'ground_handling_multiplier' => 150.00],
    ['airline_id' => 1, 'type' => '772-36ER-GE90-115B', 'name' => '777-367 ER GE90-115B',  'cost_block_hour' => 100.00,  'cost_delay_minute' => 0.00, 'ground_handling_multiplier' => 150.00],
    ['airline_id' => 1, 'type' => 'A320',              'name' => 'A320',                  'cost_block_hour' => 2300.00, 'fuel_type' => 1,            'ground_handling_multiplier' => 100.00],
];

$subfleetIdsByType = [];
foreach ($subfleetSeed as $subfleet) {
    $subfleetIdsByType[$subfleet['type']] = Subfleet::updateOrCreate(
        ['airline_id' => $subfleet['airline_id'], 'type' => $subfleet['type']],
        $subfleet
    )->id;
}

echo 'Subfleets total: '.Subfleet::withoutGlobalScopes()->count().PHP_EOL;

// Aircraft.id auto_increment + not fillable; lookup by registration (unique). IDs captured for round-robin assignment.
$aircraftSeed = [
    ['subfleet_id' => $subfleetIdsByType['744-3X-RB211'], 'icao' => 'B744', 'iata' => '744', 'airport_id' => 'KJFK', 'name' => 'Boeing 747-438', 'registration' => '001Z',   'flight_time' => 540, 'status' => 'A', 'state' => 0],
    ['subfleet_id' => $subfleetIdsByType['744-3X-RB211'], 'icao' => 'B744', 'iata' => '744', 'airport_id' => 'KAUS', 'name' => 'Boeing 747-412', 'registration' => 'S2333',  'flight_time' => 180, 'status' => 'A', 'state' => 0],
    ['subfleet_id' => $subfleetIdsByType['A320'],         'icao' => 'A320', 'iata' => '320', 'airport_id' => 'EGLL', 'name' => 'Airbus A320',    'registration' => 'N786DL', 'flight_time' => 0,   'status' => 'A', 'state' => 0, 'hex_code' => 'b47165dd', 'mtow' => 78800.00, 'zfw' => 62500.00],
];

$aircraftIds = [];
foreach ($aircraftSeed as $a) {
    $aircraftIds[] = Aircraft::updateOrCreate(['registration' => $a['registration']], $a)->id;
}

echo 'Aircraft total: '.Aircraft::withoutGlobalScopes()->count().PHP_EOL;
echo 'Aircraft IDs assigned: '.implode(', ', $aircraftIds).PHP_EOL;

// Last two routes (700, 800) seeded PENDING for admin accept/reject; rest ACCEPTED.
// Tuple: [departure, arrival, flight_number, flight_time_minutes]
$routes = [
    ['KJFK', 'KLAX', 100, 330],
    ['KLAX', 'KJFK', 101, 320],
    ['KAUS', 'KSEA', 200, 240],
    ['KSEA', 'KAUS', 201, 235],
    ['KJFK', 'EGLL', 400, 410],
    ['EGLL', 'KJFK', 401, 460],
    ['KLAX', 'KSAN', 500, 35],
    ['KLGA', 'KPAE', 600, 305],
    ['KJFK', 'OMDB', 700, 720],
    ['KAUS', 'MKJP', 800, 195],
];

$lastTwoIndex = count($routes) - 2;

foreach ($routes as $i => [$dpt, $arr, $flightNum, $flightTime]) {
    $blockOff = Carbon::now()->subDays(14 - $i)->setTime(8 + $i, 0, 0);
    $blockOn = $blockOff->copy()->addMinutes($flightTime);
    $blockOffStr = $blockOff->toDateTimeString();
    $blockOnStr = $blockOn->toDateTimeString();

    $state = $i >= $lastTwoIndex ? PirepState::PENDING : PirepState::ACCEPTED;

    Pirep::create([
        'id'                  => (string) Str::uuid(),
        'user_id'             => 1,
        'airline_id'          => 1,
        'aircraft_id'         => $aircraftIds[$i % count($aircraftIds)],
        'flight_number'       => (string) $flightNum,
        'flight_type'         => 'J',
        'dpt_airport_id'      => $dpt,
        'arr_airport_id'      => $arr,
        'flight_time'         => $flightTime,
        'planned_flight_time' => $flightTime,
        'block_fuel'          => 20000 + ($i * 500),
        'fuel_used'           => 15000 + ($i * 400),
        'distance'            => $flightTime * 7.5,
        'planned_distance'    => $flightTime * 7.5,
        'level'               => 36000,
        'landing_rate'        => -150 - ($i * 10),
        'score'               => 90 - $i,
        'source'              => 0,
        'source_name'         => 'Manual seed',
        'state'               => $state->value,
        'status'              => PirepStatus::ARRIVED->value,
        'notes'               => 'Seeded PIREP #'.$flightNum,
        'block_off_time'      => $blockOffStr,
        'block_on_time'       => $blockOnStr,
        'submitted_at'        => $blockOnStr,
        'created_at'          => $blockOffStr,
        'updated_at'          => $blockOnStr,
    ]);
}

echo 'PIREPs total: '.Pirep::count().PHP_EOL;
echo 'Admin PIREPs: '.Pirep::where('user_id', 1)->count().PHP_EOL;

<?php

declare(strict_types=1);

use App\Models\Airline;
use App\Models\Airport;
use App\Models\FlightBundle;
use App\Support\FlightTimeBackfiller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Insert a flight row directly via query builder, bypassing the Flight model's
 * dpt_time/arr_time mutators (which write to departure_time/arrival_time instead
 * of the legacy columns). The backfill reads the raw dpt_time column via
 * DB::table(), so test data must be inserted the same way.
 */
function insertLegacyFlight(array $attributes): int
{
    $airline = Airline::factory()->create();
    $dpt = Airport::factory()->create();
    $arr = Airport::factory()->create();
    $bundle = FlightBundle::factory()->create(['is_default' => true]);

    $defaults = [
        'id'             => fake()->unique()->numberBetween(10, 10000000),
        'airline_id'     => (int) $airline->getKey(),
        'flight_number'  => fake()->unique()->numberBetween(10, 1000000),
        'dpt_airport_id' => (int) $dpt->getKey(),
        'arr_airport_id' => (int) $arr->getKey(),
        'bundle_id'      => (int) $bundle->getKey(),
        'distance'       => 100,
        'flight_time'    => 120,
        'enabled'        => 1,
        'visible'        => 1,
        'days'           => 0,
        'dpt_time'       => null,
        'arr_time'       => null,
        'departure_time' => null,
        'arrival_time'   => null,
    ];

    $data = array_merge($defaults, $attributes);

    DB::table('flights')->insert($data);

    return (int) $data['id'];
}

it('backfills departure_time from various dpt_time formats and warns on unparseable input', function (): void {
    Log::shouldReceive('channel')->andReturnSelf()->zeroOrMoreTimes();
    Log::shouldReceive('warning')->zeroOrMoreTimes();
    Log::shouldReceive('info')->zeroOrMoreTimes();

    $f1 = insertLegacyFlight(['dpt_time' => '0800']);
    $f2 = insertLegacyFlight(['dpt_time' => '08:00']);
    $f3 = insertLegacyFlight(['dpt_time' => '8am']);
    $f4 = insertLegacyFlight(['dpt_time' => 'not a time']);

    $result = FlightTimeBackfiller::run();

    expect($result)->toBe(['parsed' => 3, 'failures' => 1]);

    expect(DB::table('flights')->where('id', $f1)->value('departure_time'))->toBe('08:00:00')
        ->and(DB::table('flights')->where('id', $f2)->value('departure_time'))->toBe('08:00:00')
        ->and(DB::table('flights')->where('id', $f3)->value('departure_time'))->toBe('08:00:00')
        ->and(DB::table('flights')->where('id', $f4)->value('departure_time'))->toBeNull();
});

it('skips flights that already have departure_time set', function (): void {
    Log::shouldReceive('channel')->andReturnSelf()->zeroOrMoreTimes();
    Log::shouldReceive('warning')->zeroOrMoreTimes();
    Log::shouldReceive('info')->zeroOrMoreTimes();

    $id = insertLegacyFlight([
        'dpt_time'       => '0900',
        'departure_time' => '09:00:00',
    ]);

    $result = FlightTimeBackfiller::run();

    expect($result)->toBe(['parsed' => 0, 'failures' => 0])
        ->and(DB::table('flights')->where('id', $id)->value('departure_time'))->toBe('09:00:00');
});

<?php

declare(strict_types=1);

use App\Models\Flight;
use Illuminate\Support\Facades\DB;

test('accessor returns legacy Hi format from new column', function (): void {
    $flight = Flight::factory()->make([
        'departure_time' => '08:30:00',
        'dpt_time'       => '',
    ]);

    expect($flight->dpt_time)->toBe('0830');
});

test('accessor returns null when new column is null', function (): void {
    $flight = Flight::factory()->make([
        'departure_time' => null,
    ]);

    expect($flight->dpt_time)->toBeNull();
});

test('arr_time accessor returns legacy Hi format', function (): void {
    $flight = Flight::factory()->make([
        'arrival_time' => '14:15:00',
        'arr_time'     => '',
    ]);

    expect($flight->arr_time)->toBe('1415');
});

test('arr_time accessor returns null when new column is null', function (): void {
    $flight = Flight::factory()->make([
        'arrival_time' => null,
    ]);

    expect($flight->arr_time)->toBeNull();
});

test('mutator parses and writes to new column on mass assignment', function (): void {
    $flight = Flight::factory()->create([
        'dpt_time' => '08:30',
    ]);

    expect($flight->departure_time->format('H:i:s'))->toBe('08:30:00');
});

test('mutator parses and writes to new column on direct assignment', function (): void {
    $flight = Flight::factory()->create();
    $flight->arr_time = '14:15';
    $flight->save();

    expect($flight->fresh()->arrival_time->format('H:i:s'))->toBe('14:15:00');
});

test('mutator sets null for unparseable input', function (): void {
    $flight = Flight::factory()->create([
        'dpt_time' => 'not a time',
    ]);

    expect($flight->fresh()->departure_time)->toBeNull();
});

test('mutator handles Hi format on mass assignment', function (): void {
    $flight = Flight::factory()->create([
        'dpt_time' => '0830',
    ]);

    expect($flight->departure_time->format('H:i:s'))->toBe('08:30:00');
});

test('mutator handles AM/PM format', function (): void {
    $flight = Flight::factory()->create([
        'dpt_time' => '8:30 AM',
    ]);

    expect($flight->departure_time->format('H:i:s'))->toBe('08:30:00');
});

test('mutator handles single hour format', function (): void {
    $flight = Flight::factory()->create([
        'dpt_time' => '8',
    ]);

    expect($flight->departure_time->format('H:i:s'))->toBe('08:00:00');
});

test('mutator does not write legacy dpt_time column', function (): void {
    $flight = Flight::factory()->create([
        'dpt_time' => '0830',
    ]);

    $rawDptTime = DB::table('flights')->where('id', $flight->id)->value('dpt_time');
    $rawDepartureTime = DB::table('flights')->where('id', $flight->id)->value('departure_time');

    expect($rawDptTime)->toBeNull()
        ->and($rawDepartureTime)->toBe('08:30:00');
});

test('mutator does not write legacy arr_time column', function (): void {
    $flight = Flight::factory()->create([
        'arr_time' => '14:15',
    ]);

    $rawArrTime = DB::table('flights')->where('id', $flight->id)->value('arr_time');
    $rawArrivalTime = DB::table('flights')->where('id', $flight->id)->value('arrival_time');

    expect($rawArrTime)->toBeNull()
        ->and($rawArrivalTime)->toBe('14:15:00');
});

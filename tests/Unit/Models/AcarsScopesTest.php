<?php

declare(strict_types=1);

use App\Models\Acars;
use App\Models\Enums\AcarsType;

test('Acars::forPirep returns only matching pirep rows', function () {
    Acars::factory()->create([
        'id'       => 'ACARS-1',
        'pirep_id' => 'PIREP-A',
        'type'     => AcarsType::FLIGHT_PATH,
    ]);
    Acars::factory()->create([
        'id'       => 'ACARS-2',
        'pirep_id' => 'PIREP-B',
        'type'     => AcarsType::FLIGHT_PATH,
    ]);

    $results = Acars::forPirep('PIREP-A')->pluck('id')->all();

    expect($results)->toBe(['ACARS-1']);
});

test('Acars::ofType returns only matching ACARS entry types', function () {
    Acars::factory()->create([
        'id'       => 'ACARS-3',
        'pirep_id' => 'PIREP-A',
        'type'     => AcarsType::LOG,
    ]);
    Acars::factory()->create([
        'id'       => 'ACARS-4',
        'pirep_id' => 'PIREP-A',
        'type'     => AcarsType::ROUTE,
    ]);

    $results = Acars::ofType(AcarsType::LOG)->pluck('id')->all();

    expect($results)->toBe(['ACARS-3']);
});

test('Acars::orderedBySimTime sorts rows in ascending sim time order', function () {
    Acars::factory()->create([
        'id'       => 'ACARS-5',
        'pirep_id' => 'PIREP-A',
        'type'     => AcarsType::FLIGHT_PATH,
        'sim_time' => '10:05:00',
    ]);
    Acars::factory()->create([
        'id'       => 'ACARS-6',
        'pirep_id' => 'PIREP-A',
        'type'     => AcarsType::FLIGHT_PATH,
        'sim_time' => '10:00:00',
    ]);
    Acars::factory()->create([
        'id'       => 'ACARS-7',
        'pirep_id' => 'PIREP-A',
        'type'     => AcarsType::FLIGHT_PATH,
        'sim_time' => '10:03:00',
    ]);

    $results = Acars::orderedBySimTime()->pluck('id')->all();

    expect($results)->toBe(['ACARS-6', 'ACARS-7', 'ACARS-5']);
});

<?php

use App\Models\Fare;
use App\Models\Subfleet;
use App\Services\FareService;

test('subfleet fares no override', function () {
    $fare_svc = app(FareService::class);

    $subfleet = Subfleet::factory()->hasAircraft(1)->create();

    $fare = Fare::factory()->create();

    $fare_svc->setForSubfleet($subfleet, $fare);
    $subfleet_fares = $fare_svc->getForSubfleet($subfleet);

    expect($subfleet_fares)->toHaveCount(1)
        ->and($subfleet_fares->get(0)->price)->toEqual($fare->price)
        ->and($subfleet_fares->get(0)->capacity)->toEqual($fare->capacity);

    //
    // set an override now
    //
    $fare_svc->setForSubfleet($subfleet, $fare, [
        'price' => 50, 'capacity' => 400,
    ]);

    // look for them again
    $subfleet_fares = $fare_svc->getForSubfleet($subfleet);

    expect($subfleet_fares)->toHaveCount(1)
        ->and($subfleet_fares[0]->price)->toEqual(50)
        ->and($subfleet_fares[0]->capacity)->toEqual(400);

    // delete
    $fare_svc->delFareFromSubfleet($subfleet, $fare);
    expect($fare_svc->getForSubfleet($subfleet))->toHaveCount(0);
});

<?php

use App\Models\Award;
use App\Models\Pirep;
use App\Models\User;
use App\Models\UserAward;
use App\Services\AwardService;
use App\Services\PirepService;
use Modules\Awards\Awards\FlightRouteAwards;
use Modules\Awards\Awards\PilotFlightAwards;

beforeEach(function (): void {
    loadYamlIntoDb('fleet');
});

test('get awards classes', function () {
    $classes = app(AwardService::class)->findAllAwardClasses();
    expect($classes)->toBeGreaterThanOrEqual(2);
});

test('awards given', function () {
    // Create one award that's given out with one flight
    $award = Award::factory()->create([
        'ref_model_type'   => PilotFlightAwards::class,
        'ref_model_params' => 1,
    ]);

    $user = User::factory()->create([
        'flights' => 0,
    ]);

    $pirep = Pirep::factory()->create([
        'airline_id' => $user->airline->id,
        'user_id'    => $user->id,
    ]);

    $pirepSvc = app(PirepService::class);
    $pirepSvc->create($pirep);
    $pirepSvc->accept($pirep);

    $w = [
        'user_id'  => $user->id,
        'award_id' => $award->id,
    ];

    // Make sure only one is awarded
    expect(UserAward::where($w)->count(['id']))->toEqual(1);

    $found_award = UserAward::where($w)->first();
    expect($found_award)->not->toBeNull();
});

test('flight route award', function () {
    /** @var User $user */
    $user = User::factory()->create([
        'flights' => 0,
    ]);

    /** @var Award $award */
    $award = Award::factory()->create([
        'ref_model_type'   => FlightRouteAwards::class,
        'ref_model_params' => 1,
    ]);

    /** @var Pirep $pirep */
    $pirep = Pirep::factory()->create([
        'airline_id' => $user->airline->id,
        'user_id'    => $user->id,
    ]);

    $flightAward = new FlightRouteAwards($award, $user);

    // Test no last PIREP for the user
    expect($flightAward->check(''))->toBeFalse();

    // Reinit award, add a last user PIREP id
    $user->last_pirep_id = $pirep->id;
    $user->save();

    $flightAward = new FlightRouteAwards($award, $user);
    $validStrs = [
        $pirep->dpt_airport_id.':'.$pirep->arr_airport_id,
        $pirep->dpt_airport_id.':'.$pirep->arr_airport_id.' ',
        $pirep->dpt_airport_id.':'.$pirep->arr_airport_id.':',
        strtolower($pirep->dpt_airport_id).':'.strtolower($pirep->arr_airport_id),
    ];

    foreach ($validStrs as $str) {
        expect($flightAward->check($str))->toBeTrue();
    }

    // Check error conditions
    $errStrs = [
        '',
        ' ',
        ':',
        'ABCD:EDFSDF',
        $pirep->dpt_airport_id.':',
        ':'.$pirep->arr_airport_id,
        ':'.$pirep->arr_airport_id.':',
    ];

    foreach ($errStrs as $err) {
        expect($flightAward->check($err))->toBeFalse();
    }
});

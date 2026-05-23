<?php

use App\Models\Flight;
use App\Models\User;
use App\Support\SubfleetAccessPolicy;

it('reads settings into typed flags', function (): void {
    updateSetting('pireps.restrict_aircraft_to_rank', true);
    updateSetting('pireps.restrict_aircraft_to_typerating', false);
    updateSetting('pireps.only_aircraft_at_dpt_airport', true);
    updateSetting('bids.block_aircraft', false);

    $user = User::factory()->make();
    $policy = new SubfleetAccessPolicy($user);

    expect($policy->rankRestricted)->toBeTrue()
        ->and($policy->typeRatingRestricted)->toBeFalse()
        ->and($policy->restrictToDepartureAirport)->toBeTrue()
        ->and($policy->blockBookedAircraft)->toBeFalse();
});

it('exposes the constructed user and flight', function (): void {
    $user = User::factory()->make();
    $flight = Flight::factory()->make();

    $policy = new SubfleetAccessPolicy($user, $flight);

    expect($policy->user)->toBe($user)
        ->and($policy->flight)->toBe($flight);

    $policyNoFlight = new SubfleetAccessPolicy($user);
    expect($policyNoFlight->flight)->toBeNull();
});

it('inverts flags when settings flip', function (): void {
    updateSetting('pireps.restrict_aircraft_to_rank', false);
    updateSetting('pireps.restrict_aircraft_to_typerating', true);
    updateSetting('pireps.only_aircraft_at_dpt_airport', false);
    updateSetting('bids.block_aircraft', true);

    $policy = new SubfleetAccessPolicy(User::factory()->make());

    expect($policy->rankRestricted)->toBeFalse()
        ->and($policy->typeRatingRestricted)->toBeTrue()
        ->and($policy->restrictToDepartureAirport)->toBeFalse()
        ->and($policy->blockBookedAircraft)->toBeTrue();
});

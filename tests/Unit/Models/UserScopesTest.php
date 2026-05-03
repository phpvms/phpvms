<?php

declare(strict_types=1);

use App\Models\Airline;
use App\Models\Enums\UserState;
use App\Models\User;

test('User scopes compose with each other', function () {
    $airline = Airline::factory()->create();
    User::factory()->create(['name' => 'match',          'state' => UserState::ACTIVE,  'airline_id' => $airline->id]);
    User::factory()->create(['name' => 'wrong-state',    'state' => UserState::PENDING, 'airline_id' => $airline->id]);
    User::factory()->create(['name' => 'wrong-airline',  'state' => UserState::ACTIVE,  'airline_id' => Airline::factory()->create()->id]);

    $results = User::active()->forAirline($airline->id)->pluck('name')->all();

    expect($results)->toBe(['match']);
});

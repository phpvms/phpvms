<?php

declare(strict_types=1);

require_once __DIR__.'/../Support.php';
require_once __DIR__.'/../Flights/Support.php';

use App\Models\User;

/**
 * 8.2: the pages skylight has no design for. Only the roster ordering rule is behaviour.
 */
beforeEach(function (): void {
    useAirside();
    airsideFlightsBaseline();
    $this->withoutVite();
});

it('sorts the roster by the query string', function (): void {
    $first = User::factory()->create(['name' => 'Alpha Pilot', 'flights' => 5]);
    $second = User::factory()->create(['name' => 'Zulu Pilot', 'flights' => 9]);
    $this->actingAs($first);

    $names = fn ($response): array => $response->viewData('users')->getCollection()->pluck('name')->intersect([$first->name, $second->name])->values()->all();

    expect($names($this->get(route('frontend.users.index', ['orderBy' => 'flights', 'sortedBy' => 'desc']))->assertOk()))
        ->toBe([$second->name, $first->name]);

    expect($names($this->get(route('frontend.users.index', ['orderBy' => 'flights', 'sortedBy' => 'asc']))->assertOk()))
        ->toBe([$first->name, $second->name]);
});

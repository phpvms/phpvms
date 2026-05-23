<?php

declare(strict_types=1);

use App\Models\Flight;
use App\Models\User;

/*
 * Locks in the JSON response shape of GET /api/flights.
 *
 * This is the contract external API consumers (ACARS clients, pilot apps,
 * third-party integrations) depend on. Must remain stable across the
 * repository-removal refactor (Phases 1-7).
 *
 * Unlike Characterization tests, this file STAYS — it's the permanent
 * contract test for the public API.
 */

test('flight list returns expected json structure', function (): void {
    /** @var User $user */
    $user = User::factory()->create();

    // Create flights belonging to the user's airline so they pass
    // the pilots.restrict_to_company filter (if it's enabled).
    Flight::factory()->count(3)->create([
        'airline_id' => $user->airline_id,
        'enabled'    => true,
        'visible'    => true,
    ]);

    $response = $this->withHeader('Authorization', $user->api_key)->get('/api/flights');

    $response->assertOk();
    $response->assertJsonStructure([
        'data' => [
            '*' => [
                'id',
                'airline_id',
                'flight_number',
                'dpt_airport_id',
                'arr_airport_id',
                'distance' => [
                    'm',
                    'km',
                    'mi',
                    'nmi',
                ],
                'flight_time',
                'departure_time',
                'arrival_time',
                'dpt_time',
                'arr_time',
                'active',
                'enabled',
                'visible',
                'ident',
                'load_factor',
                'load_factor_variance',
                'airline',
                'fields',
            ],
        ],
        'links' => [
            'first',
            'last',
            'prev',
            'next',
        ],
        'meta' => [
            'current_page',
            'from',
            'last_page',
            'path',
            'per_page',
            'to',
            'total',
        ],
    ]);
});

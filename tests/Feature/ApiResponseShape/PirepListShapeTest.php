<?php

declare(strict_types=1);

use App\Enums\PirepState;
use App\Models\Pirep;
use App\Models\User;

/*
 * Locks in the JSON response shape of GET /api/pireps.
 *
 * This is the contract external API consumers (ACARS clients, pilot apps,
 * third-party integrations) depend on. Must remain stable across the
 * repository-removal refactor (Phases 1-7).
 *
 * Note: /api/pireps is routed to UserController::pireps which scopes
 * results to the authenticated user (user_id) and excludes CANCELLED state
 * by default.
 *
 * Unlike Characterization tests, this file STAYS — it's the permanent
 * contract test for the public API.
 */

test('pirep list returns expected json structure', function (): void {
    /** @var User $user */
    $user = User::factory()->create();

    // Create pireps belonging to the authenticated user, in a non-CANCELLED
    // state so they are returned by the endpoint's default filter.
    Pirep::factory()->count(2)->create([
        'user_id' => $user->id,
        'state'   => PirepState::ACCEPTED,
    ]);

    $response = $this->withHeader('Authorization', $user->api_key)->get('/api/pireps');

    $response->assertOk();
    $response->assertJsonStructure([
        'data' => [
            '*' => [
                'id',
                'user_id',
                'airline_id',
                'aircraft_id',
                'flight_id',
                'flight_number',
                'dpt_airport_id',
                'arr_airport_id',
                'distance' => [
                    'm',
                    'km',
                    'mi',
                    'nmi',
                ],
                'planned_distance' => [
                    'm',
                    'km',
                    'mi',
                    'nmi',
                ],
                'block_fuel' => [
                    'kg',
                    'lbs',
                ],
                'fuel_used' => [
                    'kg',
                    'lbs',
                ],
                'flight_time',
                'state',
                'status',
                'ident',
                'phase',
                'status_text',
                'aircraft',
                'airline',
                'dpt_airport',
                'arr_airport',
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

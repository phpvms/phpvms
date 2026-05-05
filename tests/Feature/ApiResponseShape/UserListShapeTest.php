<?php

declare(strict_types=1);

use App\Models\User;

/*
 * Locks in the JSON response shape of the public User API.
 *
 * NOTE: phpVMS does not expose a paginated GET /api/users index endpoint.
 * The user-shaped public-API surface that external consumers (ACARS
 * clients, pilot apps) depend on is GET /api/user — the authenticated
 * user's profile resource. The same UserResource is also returned by
 * /api/users/me and /api/users/{id}, so locking its shape here covers
 * the User contract for the entire public API.
 *
 * Must remain stable across the repository-removal refactor (Phases 1-7).
 *
 * Unlike Characterization tests, this file STAYS — it's the permanent
 * contract test for the public API.
 */

test('user resource returns expected json structure', function (): void {
    /** @var User $user */
    $user = User::factory()->create();

    $response = $this->withHeader('Authorization', $user->api_key)->get('/api/user');

    $response->assertOk();
    $response->assertJsonStructure([
        'data' => [
            'id',
            'pilot_id',
            'ident',
            'name',
            'name_private',
            'avatar',
            'discord_id',
            'vatsim_id',
            'ivao_id',
            'simbrief_username',
            'rank_id',
            'home_airport',
            'curr_airport',
            'last_pirep_id',
            'flights',
            'flight_time',
            'transfer_time',
            'total_time',
            'timezone',
            'state',
            'airline',
            'bids',
            'rank',
        ],
    ]);
});

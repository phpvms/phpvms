<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\UserIdentity;

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

test('user resource returns provider subjects from linked identities', function (): void {
    $user = User::factory()->create();

    foreach (['discord', 'vatsim', 'ivao'] as $connectionId) {
        UserIdentity::query()->create([
            'user_id'          => $user->id,
            'connection_id'    => $connectionId,
            'provider_user_id' => $connectionId.'-subject',
        ]);
    }

    $this->withHeader('Authorization', $user->api_key)
        ->get('/api/user')
        ->assertOk()
        ->assertJsonPath('data.discord_id', 'discord-subject')
        ->assertJsonPath('data.vatsim_id', 'vatsim-subject')
        ->assertJsonPath('data.ivao_id', 'ivao-subject');
});

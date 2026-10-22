<?php

declare(strict_types=1);

use App\Features\OAuth\Helpers\ExternalAuthSessionService;
use App\Features\OAuth\Helpers\OAuthConnectionService;
use App\Models\ExternalAuthSession;
use App\Models\OAuthConnection;
use App\Models\User;
use App\Models\UserIdentity;
use Illuminate\Session\SessionManager;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

function backChannelConnection(string $connectionId): OAuthConnection
{
    return OAuthConnection::query()->create([
        'connection_id' => $connectionId,
        'display_name'  => $connectionId,
        'provider'      => 'openidconnect',
        'client_id'     => $connectionId.'-client',
        'client_secret' => 'secret',
        'configuration' => ['base_url' => 'https://issuer.example.com'],
        'enabled'       => true,
    ]);
}

function persistLocalSession(string $sessionId, User $user): void
{
    DB::table('sessions')->insert([
        'id'            => $sessionId,
        'user_id'       => $user->id,
        'ip_address'    => '127.0.0.1',
        'user_agent'    => 'Pest',
        'payload'       => base64_encode('session'),
        'last_activity' => time(),
    ]);
}

it('exposes back-channel logout as POST only', function (): void {
    $this->get('/oauth/crew-sso/backchannel-logout')
        ->assertStatus(Response::HTTP_METHOD_NOT_ALLOWED);
});

it('does not accept a browser session as logout proof', function (): void {
    backChannelConnection('crew-sso');
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/oauth/crew-sso/backchannel-logout')
        ->assertStatus(Response::HTTP_BAD_REQUEST);
});

it('throttles repeated back-channel logout requests', function (): void {
    backChannelConnection('crew-sso');
    User::factory()->create();

    foreach (range(1, 60) as $_) {
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.123'])
            ->postJson('/oauth/crew-sso/backchannel-logout')
            ->assertStatus(Response::HTTP_BAD_REQUEST);
    }

    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.123'])
        ->postJson('/oauth/crew-sso/backchannel-logout')
        ->assertTooManyRequests();
});

it('records an external session and updates its OIDC identifiers', function (): void {
    $connection = backChannelConnection('crew-sso');
    $user = User::factory()->create();
    $service = app(ExternalAuthSessionService::class);

    $service->record($user, $connection, 'local-session', 'user-123', null);
    $service->record($user, $connection, 'local-session', 'user-123', 'oidc-session');

    expect(ExternalAuthSession::query()->count())->toBe(1)
        ->and(ExternalAuthSession::query()->firstOrFail()->oidc_sid)->toBe('oidc-session');
});

it('revokes only sessions matching the connection and OIDC session identifier', function (): void {
    config(['session.driver' => 'database']);
    app(SessionManager::class)->forgetDrivers();

    $connection = backChannelConnection('crew-sso');
    $otherConnection = backChannelConnection('other-sso');
    $user = User::factory()->create();
    UserIdentity::query()->create([
        'user_id'          => $user->id,
        'connection_id'    => $connection->connection_id,
        'provider_user_id' => 'user-123',
    ]);

    foreach (['matching', 'same-subject', 'other-connection'] as $sessionId) {
        persistLocalSession($sessionId, $user);
    }

    ExternalAuthSession::query()->insert([
        [
            'user_id'          => $user->id,
            'connection_id'    => $connection->connection_id,
            'provider_user_id' => 'user-123',
            'oidc_sid'         => 'target-sid',
            'session_id'       => 'matching',
        ],
        [
            'user_id'          => $user->id,
            'connection_id'    => $connection->connection_id,
            'provider_user_id' => 'user-123',
            'oidc_sid'         => 'other-sid',
            'session_id'       => 'same-subject',
        ],
        [
            'user_id'          => $user->id,
            'connection_id'    => $otherConnection->connection_id,
            'provider_user_id' => 'user-123',
            'oidc_sid'         => 'target-sid',
            'session_id'       => 'other-connection',
        ],
    ]);

    $count = app(ExternalAuthSessionService::class)->revoke($connection, 'target-sid', 'user-123');

    expect($count)->toBe(1)
        ->and(DB::table('sessions')->where('id', 'matching')->exists())->toBeFalse()
        ->and(DB::table('sessions')->where('id', 'same-subject')->exists())->toBeTrue()
        ->and(DB::table('sessions')->where('id', 'other-connection')->exists())->toBeTrue()
        ->and(ExternalAuthSession::query()->where('session_id', 'matching')->exists())->toBeFalse()
        ->and(ExternalAuthSession::query()->count())->toBe(2)
        ->and(UserIdentity::query()->where('connection_id', 'crew-sso')->exists())->toBeTrue();
});

it('revokes connection sessions by subject when no OIDC session identifier is supplied', function (): void {
    config(['session.driver' => 'database']);
    app(SessionManager::class)->forgetDrivers();

    $connection = backChannelConnection('crew-sso');
    $user = User::factory()->create();

    foreach (['first-session', 'second-session', 'unrelated-session'] as $sessionId) {
        persistLocalSession($sessionId, $user);
    }

    foreach ([
        ['first-session', 'user-123'],
        ['second-session', 'user-123'],
        ['unrelated-session', 'user-456'],
    ] as [$sessionId, $providerUserId]) {
        ExternalAuthSession::query()->create([
            'user_id'          => $user->id,
            'connection_id'    => $connection->connection_id,
            'provider_user_id' => $providerUserId,
            'oidc_sid'         => null,
            'session_id'       => $sessionId,
        ]);
    }

    $count = app(ExternalAuthSessionService::class)->revoke($connection, null, 'user-123');

    expect($count)->toBe(2)
        ->and(DB::table('sessions')->where('id', 'first-session')->exists())->toBeFalse()
        ->and(DB::table('sessions')->where('id', 'second-session')->exists())->toBeFalse()
        ->and(DB::table('sessions')->where('id', 'unrelated-session')->exists())->toBeTrue()
        ->and(ExternalAuthSession::query()->where('session_id', 'unrelated-session')->exists())->toBeTrue();
});

it('revokes every mapped local session before deleting a connection', function (): void {
    config(['session.driver' => 'database']);
    app(SessionManager::class)->forgetDrivers();

    $connection = backChannelConnection('crew-sso');
    $otherConnection = backChannelConnection('other-sso');
    $user = User::factory()->create();

    foreach (['first-session', 'second-session', 'other-session'] as $sessionId) {
        persistLocalSession($sessionId, $user);
    }

    foreach ([
        [$connection, 'first-session'],
        [$connection, 'second-session'],
        [$otherConnection, 'other-session'],
    ] as [$mappedConnection, $sessionId]) {
        ExternalAuthSession::query()->create([
            'user_id'          => $user->id,
            'connection_id'    => $mappedConnection->connection_id,
            'provider_user_id' => 'external-user',
            'oidc_sid'         => null,
            'session_id'       => $sessionId,
        ]);
    }

    app(OAuthConnectionService::class)->delete($connection);

    expect(DB::table('sessions')->whereIn('id', ['first-session', 'second-session'])->exists())->toBeFalse()
        ->and(DB::table('sessions')->where('id', 'other-session')->exists())->toBeTrue()
        ->and(ExternalAuthSession::query()->where('connection_id', 'crew-sso')->exists())->toBeFalse()
        ->and(ExternalAuthSession::query()->where('connection_id', 'other-sso')->exists())->toBeTrue()
        ->and(OAuthConnection::query()->where('connection_id', 'crew-sso')->exists())->toBeFalse();
});

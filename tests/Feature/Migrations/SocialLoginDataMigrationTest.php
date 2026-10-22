<?php

declare(strict_types=1);

use App\Models\ExternalAuthSession;
use App\Models\OAuthConnection;
use App\Models\User;
use App\Models\UserIdentity;
use App\Models\UserOAuthToken;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

it('creates database-backed social login connections with encrypted attributes', function (): void {
    expect(Schema::hasColumns('oauth_connections', [
        'connection_id',
        'display_name',
        'provider',
        'client_id',
        'client_secret',
        'scopes',
        'configuration',
        'enabled',
        'login_enabled',
        'registration_enabled',
        'linking_enabled',
        'managed_by',
        'sort_order',
    ]))->toBeTrue();

    expect(OAuthConnection::query()->whereIn('connection_id', ['discord', 'vatsim', 'ivao'])->count())->toBe(3);

    $connection = OAuthConnection::query()->create([
        'connection_id' => 'test-oidc',
        'display_name'  => 'Test OIDC',
        'provider'      => 'openidconnect',
        'client_id'     => 'client-id',
        'client_secret' => 'client-secret',
        'scopes'        => ['openid', 'profile', 'email'],
        'configuration' => ['issuer' => 'https://auth.example.test'],
    ]);
    $stored = DB::table('oauth_connections')->where('id', $connection->id)->first();

    expect($stored->client_secret)->not->toBe('client-secret')
        ->and($stored->configuration)->not->toContain('auth.example.test')
        ->and($connection->fresh()->client_secret)->toBe('client-secret')
        ->and($connection->fresh()->configuration)->toBe(['issuer' => 'https://auth.example.test']);
});

it('enforces both external identity ownership constraints', function (): void {
    $first = User::factory()->create();
    $second = User::factory()->create();

    UserIdentity::query()->create([
        'user_id'          => $first->id,
        'connection_id'    => 'discord',
        'provider_user_id' => 'subject-one',
    ]);

    expect(fn () => UserIdentity::query()->create([
        'user_id'          => $second->id,
        'connection_id'    => 'discord',
        'provider_user_id' => 'subject-one',
    ]))->toThrow(QueryException::class);

    expect(fn () => UserIdentity::query()->create([
        'user_id'          => $first->id,
        'connection_id'    => 'discord',
        'provider_user_id' => 'subject-two',
    ]))->toThrow(QueryException::class);
});

it('stores encrypted OAuth tokens once per user and connection', function (): void {
    $user = User::factory()->create();
    $token = UserOAuthToken::query()->create([
        'user_id'       => $user->id,
        'connection_id' => 'discord',
        'token'         => 'access-token',
        'refresh_token' => 'refresh-token',
        'expires_at'    => now()->addHour(),
    ]);
    $stored = DB::table('user_oauth_tokens')->where('id', $token->id)->first();

    expect($stored->token)->not->toBe('access-token')
        ->and($stored->refresh_token)->not->toBe('refresh-token')
        ->and($token->fresh()->token)->toBe('access-token')
        ->and($token->fresh()->refresh_token)->toBe('refresh-token');

    expect(fn () => UserOAuthToken::query()->create([
        'user_id'       => $user->id,
        'connection_id' => 'discord',
        'token'         => 'another-access-token',
        'refresh_token' => 'another-refresh-token',
    ]))->toThrow(QueryException::class);
});

it('encrypts existing plaintext tokens and restores them on rollback', function (): void {
    $migration = require database_path('migrations/2026_08_26_000002_migrate_user_oauth_tokens_to_connections.php');
    $migration->down();

    $user = User::factory()->create();
    $tokenId = DB::table('user_oauth_tokens')->insertGetId([
        'user_id'       => $user->id,
        'provider'      => 'discord',
        'token'         => 'legacy-access-token',
        'refresh_token' => 'legacy-refresh-token',
        'created_at'    => now(),
        'updated_at'    => now(),
    ]);

    $migration->up();
    $stored = DB::table('user_oauth_tokens')->where('id', $tokenId)->first();
    $token = UserOAuthToken::query()->findOrFail($tokenId);

    expect($stored->token)->not->toBe('legacy-access-token')
        ->and($stored->refresh_token)->not->toBe('legacy-refresh-token')
        ->and($token->connection_id)->toBe('discord')
        ->and($token->token)->toBe('legacy-access-token')
        ->and($token->refresh_token)->toBe('legacy-refresh-token');

    $migration->down();
    $rolledBack = DB::table('user_oauth_tokens')->where('id', $tokenId)->first();

    expect($rolledBack->provider)->toBe('discord')
        ->and($rolledBack->token)->toBe('legacy-access-token')
        ->and($rolledBack->refresh_token)->toBe('legacy-refresh-token');

    $migration->up();
});

it('maps external authentication sessions to revocable local sessions', function (): void {
    $user = User::factory()->create();
    $externalSession = ExternalAuthSession::query()->create([
        'user_id'          => $user->id,
        'connection_id'    => 'discord',
        'provider_user_id' => 'subject-one',
        'oidc_sid'         => 'provider-session',
        'session_id'       => 'local-session',
    ]);

    expect($externalSession->user->is($user))->toBeTrue()
        ->and($externalSession->connection->connection_id)->toBe('discord')
        ->and($user->external_auth_sessions()->where('session_id', 'local-session')->exists())->toBeTrue();
});

it('backfills unique legacy identities', function (): void {
    $migration = require database_path('migrations/2026_08_26_000004_backfill_legacy_user_identities.php');
    $migration->down();

    $user = User::factory()->create([
        'discord_id' => 'discord-subject',
        'vatsim_id'  => 'vatsim-subject',
        'ivao_id'    => 'ivao-subject',
    ]);

    $migration->up();

    expect($user->identities()->orderBy('connection_id')->pluck('provider_user_id', 'connection_id')->all())
        ->toBe([
            'discord' => 'discord-subject',
            'ivao'    => 'ivao-subject',
            'vatsim'  => 'vatsim-subject',
        ]);
});

it('reports duplicate legacy subjects without assigning an owner', function (): void {
    $migration = require database_path('migrations/2026_08_26_000004_backfill_legacy_user_identities.php');
    $migration->down();

    $first = User::factory()->create(['discord_id' => 'shared-subject']);
    $second = User::factory()->create(['discord_id' => 'shared-subject']);
    Log::spy();

    $migration->up();

    expect(UserIdentity::query()
        ->where('connection_id', 'discord')
        ->where('provider_user_id', 'shared-subject')
        ->exists())->toBeFalse();

    $conflict = DB::table('user_identity_conflicts')
        ->where('connection_id', 'discord')
        ->where('provider_user_id', 'shared-subject')
        ->first();

    expect($conflict)->not->toBeNull()
        ->and(json_decode($conflict->user_ids, true, flags: JSON_THROW_ON_ERROR))->toBe([$first->id, $second->id]);

    Log::shouldHaveReceived('warning')->once();
});

it('rolls the social login data migrations down and up in dependency order', function (): void {
    $connections = require database_path('migrations/2026_08_26_000000_create_oauth_connections_table.php');
    $identities = require database_path('migrations/2026_08_26_000001_create_user_identities_table.php');
    $tokens = require database_path('migrations/2026_08_26_000002_migrate_user_oauth_tokens_to_connections.php');
    $sessions = require database_path('migrations/2026_08_26_000003_create_external_auth_sessions_table.php');
    $backfill = require database_path('migrations/2026_08_26_000004_backfill_legacy_user_identities.php');

    $backfill->down();

    $sessions->down();
    $tokens->down();
    $identities->down();
    $connections->down();

    expect(Schema::hasTable('oauth_connections'))->toBeFalse()
        ->and(Schema::hasTable('user_identities'))->toBeFalse()
        ->and(Schema::hasTable('user_identity_conflicts'))->toBeFalse()
        ->and(Schema::hasTable('external_auth_sessions'))->toBeFalse()
        ->and(Schema::hasColumn('user_oauth_tokens', 'provider'))->toBeTrue()
        ->and(Schema::hasColumn('user_oauth_tokens', 'connection_id'))->toBeFalse();

    $connections->up();
    $identities->up();
    $tokens->up();
    $sessions->up();
    $backfill->up();

    expect(Schema::hasTable('oauth_connections'))->toBeTrue()
        ->and(Schema::hasTable('user_identities'))->toBeTrue()
        ->and(Schema::hasTable('external_auth_sessions'))->toBeTrue()
        ->and(Schema::hasColumn('user_oauth_tokens', 'connection_id'))->toBeTrue();
});

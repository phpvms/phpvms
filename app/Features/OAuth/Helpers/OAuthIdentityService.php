<?php

declare(strict_types=1);

namespace App\Features\OAuth\Helpers;

use App\Enums\UserState;
use App\Models\OAuthConnection;
use App\Models\User;
use App\Models\UserIdentity;
use App\Models\UserOAuthToken;
use App\Services\UserService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class OAuthIdentityService
{
    public function __construct(
        private UserService $users,
        private SocialiteProviderRegistry $providers,
        private OAuthConnectionService $connections,
        private ExternalAuthSessionService $externalSessions,
    ) {}

    public function findUser(string $connectionId, string $providerUserId): ?User
    {
        return UserIdentity::query()
            ->where('connection_id', $connectionId)
            ->where('provider_user_id', $providerUserId)
            ->first()
            ?->user;
    }

    /** @param array<string, bool|int|string|null> $pending */
    public function link(User $user, array $pending): UserIdentity
    {
        $identity = DB::transaction(function () use ($user, $pending): UserIdentity {
            $connectionId = (string) $pending['connection_id'];
            $providerUserId = (string) $pending['provider_user_id'];
            $connection = OAuthConnection::query()
                ->where('connection_id', $connectionId)
                ->lockForUpdate()
                ->first();

            if (!$connection instanceof OAuthConnection
                || !$this->connections->pendingIdentityMatches($connection, $pending)) {
                throw ValidationException::withMessages([
                    'identity' => 'The social login connection changed while this request was pending. Start again.',
                ]);
            }

            $subjectIdentity = UserIdentity::query()
                ->where('connection_id', $connectionId)
                ->where('provider_user_id', $providerUserId)
                ->lockForUpdate()
                ->first();

            if ($subjectIdentity !== null && $subjectIdentity->user_id !== $user->id) {
                throw ValidationException::withMessages([
                    'identity' => __('auth.oauth_identity_already_linked'),
                ]);
            }

            $userIdentity = UserIdentity::query()
                ->where('user_id', $user->id)
                ->where('connection_id', $connectionId)
                ->lockForUpdate()
                ->first();

            if ($userIdentity !== null && $userIdentity->provider_user_id !== $providerUserId) {
                throw ValidationException::withMessages([
                    'identity' => __('auth.oauth_connection_already_linked'),
                ]);
            }

            $identity = $subjectIdentity ?? $userIdentity ?? UserIdentity::query()->create([
                'user_id'          => $user->id,
                'connection_id'    => $connectionId,
                'provider_user_id' => $providerUserId,
            ]);

            $this->persistToken($user, $pending);

            return $identity;
        });

        $this->syncDiscordPrivateChannel($user, (string) $pending['connection_id']);

        return $identity;
    }

    /** @param array<string, bool|int|string|null> $credentials */
    public function syncToken(User $user, array $credentials): void
    {
        $this->persistToken($user, $credentials);
    }

    /** @param array<string, bool|int|string|null> $credentials */
    private function persistToken(User $user, array $credentials): void
    {
        if (($credentials['token'] ?? '') === '') {
            return;
        }

        UserOAuthToken::query()->updateOrCreate([
            'user_id'       => $user->id,
            'connection_id' => (string) $credentials['connection_id'],
        ], [
            'token'         => (string) $credentials['token'],
            'refresh_token' => (string) ($credentials['refresh_token'] ?? ''),
            'expires_at'    => filled($credentials['token_expires_at'] ?? null)
                ? Carbon::parse((string) $credentials['token_expires_at'])
                : null,
        ]);
    }

    public function unlink(User $user, OAuthConnection $connection): void
    {
        $identity = UserIdentity::query()
            ->where('user_id', $user->id)
            ->where('connection_id', $connection->connection_id)
            ->first();

        if ($identity === null) {
            throw ValidationException::withMessages([
                'identity' => __('auth.oauth_identity_not_linked'),
            ]);
        }

        $hasPassword = filled($user->getAuthPassword());
        $hasOtherLogin = UserIdentity::query()
            ->where('user_id', $user->id)
            ->where('connection_id', '!=', $connection->connection_id)
            ->whereHas('connection', static fn ($query) => $query
                ->where('enabled', true)
                ->where('login_enabled', true))
            ->with('connection')
            ->get()
            ->contains(fn (UserIdentity $identity): bool => $this->providers->isInstalled($identity->connection->provider));

        if (!$hasPassword && !$hasOtherLogin) {
            throw ValidationException::withMessages([
                'identity' => __('auth.oauth_unlink_last_method'),
            ]);
        }

        DB::transaction(function () use ($user, $connection, $identity): void {
            $this->externalSessions->revokeForUser($user, $connection);

            UserOAuthToken::query()
                ->where('user_id', $user->id)
                ->where('connection_id', $connection->connection_id)
                ->delete();

            $identity->delete();

            if ($connection->provider === 'discord') {
                $user->update(['discord_private_channel_id' => '']);
            }
        });
    }

    public function syncDiscordPrivateChannel(User $user, string $connectionId): void
    {
        if ($user->state !== UserState::ACTIVE && $user->state !== UserState::ON_LEAVE) {
            return;
        }

        $connection = OAuthConnection::query()
            ->where('connection_id', $connectionId)
            ->first();

        if ($connection?->provider !== 'discord') {
            return;
        }

        $discordId = UserIdentity::query()
            ->where('user_id', $user->id)
            ->where('connection_id', $connectionId)
            ->value('provider_user_id');

        $this->users->retrieveDiscordPrivateChannelId($user, is_string($discordId) ? $discordId : null);
    }
}

<?php

declare(strict_types=1);

namespace App\Features\OAuth\Helpers;

use App\Models\ExternalAuthSession;
use App\Models\OAuthConnection;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Session\SessionManager;

final readonly class ExternalAuthSessionService
{
    public function __construct(private SessionManager $sessions) {}

    public function record(
        User $user,
        OAuthConnection $connection,
        string $sessionId,
        string $providerUserId,
        ?string $oidcSid,
    ): ExternalAuthSession {
        return ExternalAuthSession::query()->updateOrCreate([
            'session_id' => $sessionId,
        ], [
            'user_id'          => $user->id,
            'connection_id'    => $connection->connection_id,
            'provider_user_id' => $providerUserId,
            'oidc_sid'         => $oidcSid,
        ]);
    }

    public function revoke(OAuthConnection $connection, ?string $oidcSid, ?string $providerUserId): int
    {
        $query = ExternalAuthSession::query()->where('connection_id', $connection->connection_id);

        if ($oidcSid !== null) {
            $query->where('oidc_sid', $oidcSid);
        } elseif ($providerUserId !== null) {
            $query->where('provider_user_id', $providerUserId);
        } else {
            return 0;
        }

        return $this->destroySessions($query->get());
    }

    public function revokeAll(OAuthConnection $connection): int
    {
        return $this->destroySessions(
            ExternalAuthSession::query()
                ->where('connection_id', $connection->connection_id)
                ->get(),
        );
    }

    public function revokeForUser(User $user, OAuthConnection $connection): int
    {
        return $this->destroySessions(
            ExternalAuthSession::query()
                ->where('user_id', $user->id)
                ->where('connection_id', $connection->connection_id)
                ->get(),
        );
    }

    /** @param Collection<int, ExternalAuthSession> $externalSessions */
    private function destroySessions(Collection $externalSessions): int
    {
        $session = $this->sessions->driver();
        $handler = $session->getHandler();
        $currentSessionId = $session->getId();

        foreach ($externalSessions as $externalSession) {
            if ($externalSession->session_id === $currentSessionId) {
                $session->invalidate();
            } else {
                $handler->destroy($externalSession->session_id);
            }

            $externalSession->delete();
        }

        return $externalSessions->count();
    }
}

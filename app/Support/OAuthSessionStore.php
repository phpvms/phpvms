<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\OAuthConnection;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Laravel\Socialite\Two\User as SocialiteUser;

final class OAuthSessionStore
{
    private const string FLOW_KEY = 'oauth.flows';

    private const string PENDING_KEY = 'oauth.pending_identity';

    private const int TTL_MINUTES = 15;

    /** @param array{invite: string, invite_token: string}|null $registrationContext */
    public function rememberFlow(
        Request $request,
        string $state,
        OAuthConnection $connection,
        string $intent,
        ?array $registrationContext = null,
    ): void {
        $flows = (array) $request->session()->get(self::FLOW_KEY, []);
        $flows[$state] = [
            'connection_id' => $connection->connection_id,
            'intent'        => $intent,
            'invite'        => $registrationContext['invite'] ?? null,
            'invite_token'  => $registrationContext['invite_token'] ?? null,
            'expires_at'    => now()->addMinutes(self::TTL_MINUTES)->getTimestamp(),
        ];

        $request->session()->put(self::FLOW_KEY, $flows);
    }

    /** @return array{connection_id: string, intent: string, invite: null|string, invite_token: null|string, expires_at: int}|null */
    public function flow(Request $request, string $state): ?array
    {
        $flow = $request->session()->get(self::FLOW_KEY.'.'.$state);
        if (!is_array($flow) || ($flow['expires_at'] ?? 0) < now()->getTimestamp()) {
            return null;
        }

        return $flow;
    }

    public function forgetFlow(Request $request, string $state): void
    {
        $request->session()->forget(self::FLOW_KEY.'.'.$state);
    }

    /**
     * @param  array<string, bool|int|string|null> $flow
     * @return array<string, bool|int|string|null>
     */
    public function rememberPending(
        Request $request,
        OAuthConnection $connection,
        SocialiteUser $providerUser,
        array $flow = [],
    ): array {
        $raw = $providerUser->getRaw();
        $emailVerified = (bool) ($raw['email_verified'] ?? $raw['verified_email'] ?? false);
        $issuer = $connection->configuration['base_url'] ?? null;
        $oidcSid = $raw['sid'] ?? null;
        $pending = [
            'connection_id'        => $connection->connection_id,
            'connection_record_id' => (int) $connection->getKey(),
            'provider'             => $connection->provider,
            'issuer'               => is_string($issuer) ? rtrim($issuer, '/') : null,
            'provider_user_id'     => (string) $providerUser->getId(),
            'oidc_sid'             => is_string($oidcSid) && $oidcSid !== '' ? $oidcSid : null,
            'name'                 => $providerUser->getName(),
            'email'                => $providerUser->getEmail(),
            'email_verified'       => $emailVerified,
            'registration_attempt' => Str::random(64),
            'invite'               => $flow['invite'] ?? null,
            'invite_token'         => $flow['invite_token'] ?? null,
            'token'                => (string) $providerUser->token,
            'refresh_token'        => (string) $providerUser->refreshToken,
            'token_expires_at'     => $providerUser->expiresIn > 0
                ? now()->addSeconds($providerUser->expiresIn)->toIso8601String()
                : null,
            'expires_at' => now()->addMinutes(self::TTL_MINUTES)->getTimestamp(),
        ];

        $storedPending = $pending;
        $storedPending['token'] = Crypt::encryptString((string) $pending['token']);
        $storedPending['refresh_token'] = Crypt::encryptString((string) $pending['refresh_token']);
        $request->session()->put(self::PENDING_KEY, $storedPending);

        return $pending;
    }

    /** @return array<string, bool|int|string|null>|null */
    public function pendingRegistration(Request $request, string $attempt): ?array
    {
        $pending = $this->pending($request);
        $pendingAttempt = $pending['registration_attempt'] ?? null;
        if (!is_string($pendingAttempt) || !hash_equals($pendingAttempt, $attempt)) {
            return null;
        }

        return $pending;
    }

    /** @return array<string, bool|int|string|null>|null */
    public function pending(Request $request): ?array
    {
        $pending = $request->session()->get(self::PENDING_KEY);
        if (!is_array($pending) || ($pending['expires_at'] ?? 0) < now()->getTimestamp()) {
            $this->forgetPending($request);

            return null;
        }

        try {
            $pending['token'] = Crypt::decryptString((string) ($pending['token'] ?? ''));
            $pending['refresh_token'] = Crypt::decryptString((string) ($pending['refresh_token'] ?? ''));
        } catch (DecryptException) {
            $this->forgetPending($request);

            return null;
        }

        return $pending;
    }

    public function forgetPending(Request $request): void
    {
        $request->session()->forget(self::PENDING_KEY);
    }
}

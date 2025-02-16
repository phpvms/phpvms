<?php

namespace App\Services;

use App\Contracts\Service;
use App\Models\UserOAuthToken;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;

class OAuthService extends Service
{
    public function refreshTokensBeforeTheyExpire(): void
    {
        // Refresh tokens that expired within the last 2 days or will expire in the next 24 hours.
        // The future limit ensures we always have a valid token by refreshing it before expiration.
        // The past limit prevents excessive retries—if a token expired more than 2 days ago,
        // we've likely already attempted to refresh it three times. Stopping retries avoids
        // an infinite loop every night.

        // Note: we will apply a different policy for IVAO tokens, they'll be refreshed every week
        // even if they expire every 30min.

        $start_date = now()->subHours(49);
        $end_date = now()->addHours(25);

        $tokens = UserOAuthToken::where(function (Builder $query) use ($start_date, $end_date) {
            return $query->whereNot('provider', 'ivao')
                ->whereBetween('expires_at', [$start_date, $end_date]);
        })
            ->orWhere(function (Builder $query) {
                return $query->where('provider', 'ivao')
                    ->whereBetween('expires_at', [now()->subDays(8), now()->subDays(6)]);
            })
            ->get();

        foreach ($tokens as $token) {
            $this->refreshToken($token);
        }
    }

    public function refreshToken(UserOAuthToken $token): UserOAuthToken
    {
        try {
            $updatedToken = Socialite::driver($token->provider)->refreshToken($token->refresh_token);

            $token->update([
                'token'         => $updatedToken->token,
                'refresh_token' => $updatedToken->refreshToken,
                'expires_at'    => now()->addSeconds($updatedToken->expiresIn),
            ]);
            Log::debug('OAuth token refresh for user_id '.$token->user_id.' and provider '.$token->provider);
        } catch (ClientException $e) {
            Log::error("Error updating OAuth tokens: {$e->getMessage()}", ['exception' => $e, 'token' => $token]);
        }

        return $token->refresh();
    }

    public function refreshTokenIfExpired(UserOAuthToken $token): UserOAuthToken
    {
        return ($token->isExpired) ? $this->refreshToken($token) : $token;
    }
}

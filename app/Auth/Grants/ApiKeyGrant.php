<?php

declare(strict_types=1);

namespace App\Auth\Grants;

use App\Auth\ScopeRepository;
use App\Enums\UserState;
use App\Http\Middleware\ApiAuth;
use App\Models\User;
use DateInterval;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Grant\AbstractGrant;
use League\OAuth2\Server\ResponseTypes\ResponseTypeInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Exchanges a per-user {@see User::$api_key} for a scoped Passport access
 * token, at the existing `POST /oauth/token` endpoint.
 *
 * Modeled on league's `PasswordGrant` (the user-bearing grant), but resolves
 * the user directly from `api_key` instead of username/password, applies the
 * same state gate as {@see ApiAuth}, and issues no
 * refresh token — the `api_key` itself is the durable, re-exchangeable
 * credential (see design.md §3). Requested scopes are permission-filtered by
 * the core {@see ScopeRepository} via `finalizeScopes()`.
 *
 * No constructor: unlike `PasswordGrant`, this grant needs no
 * `UserRepositoryInterface`/`RefreshTokenRepositoryInterface` (both unused
 * without a refresh token). `enableGrantType()` injects the scope/client/
 * access-token repositories and the signing key.
 */
final class ApiKeyGrant extends AbstractGrant
{
    /**
     * {@inheritdoc}
     */
    public function respondToAccessTokenRequest(
        ServerRequestInterface $request,
        ResponseTypeInterface $responseType,
        DateInterval $accessTokenTTL
    ): ResponseTypeInterface {
        $client = $this->validateClient($request);

        $apiKey = $this->getRequestParameter('api_key', $request)
            ?? throw OAuthServerException::invalidRequest('api_key');

        // Resolve the user by their api_key — a single indexed exact-match lookup
        // (no LIKE). A DB unique constraint guarantees at most one match (see the
        // add_users_api_key_unique_index migration).
        $user = User::where('api_key', $apiKey)->first();

        // Generic error for an unknown key or a failed state gate — never reveal
        // which check failed or echo the submitted key.
        if ($user === null || ($user->state !== UserState::ACTIVE && $user->state !== UserState::ON_LEAVE)) {
            throw OAuthServerException::invalidCredentials();
        }

        $scopes = $this->validateScopes($this->getRequestParameter('scope', $request, $this->defaultScope));

        $finalizedScopes = $this->scopeRepository->finalizeScopes(
            $scopes,
            $this->getIdentifier(),
            $client,
            (string) $user->id
        );

        $accessToken = $this->issueAccessToken($accessTokenTTL, $client, (string) $user->id, $finalizedScopes);
        $responseType->setAccessToken($accessToken);

        return $responseType;
    }

    /**
     * {@inheritdoc}
     */
    public function getIdentifier(): string
    {
        return 'api_key';
    }
}

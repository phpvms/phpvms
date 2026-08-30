<?php

declare(strict_types=1);

namespace App\Services\OAuth;

use App\Models\OAuthConnection;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Client\Factory as HttpFactory;
use stdClass;
use Throwable;

final readonly class OidcLogoutTokenValidator
{
    private const string BACKCHANNEL_LOGOUT_EVENT = 'http://schemas.openid.net/event/backchannel-logout';

    private const int MAX_LOGOUT_TOKEN_BYTES = 32768;

    private const int METADATA_CACHE_SECONDS = 3600;

    private const int JWKS_CACHE_SECONDS = 900;

    public function __construct(
        private HttpFactory $http,
        private CacheRepository $cache,
    ) {}

    /** @return array{subject: null|string, sid: null|string} */
    public function validate(OAuthConnection $connection, string $logoutToken): array
    {
        $issuer = rtrim((string) ($connection->configuration['base_url'] ?? ''), '/');
        $clientId = (string) $connection->client_id;

        if ($issuer === '' || $clientId === '' || $logoutToken === '') {
            throw new InvalidOidcLogoutToken('The OIDC connection is incomplete.');
        }

        $tokenHeader = $this->assertCompactJwt($logoutToken);

        try {
            $metadata = $this->cachedJson(
                $issuer.'/.well-known/openid-configuration',
                self::METADATA_CACHE_SECONDS,
            );

            if (($metadata['issuer'] ?? null) !== $issuer) {
                throw new InvalidOidcLogoutToken('The issuer does not match the connection.');
            }

            $jwksUri = $metadata['jwks_uri'] ?? null;
            if (!is_string($jwksUri) || $jwksUri === '') {
                throw new InvalidOidcLogoutToken('The issuer metadata has no JWKS URI.');
            }

            $jwks = $this->cachedJson($jwksUri, self::JWKS_CACHE_SECONDS);
            if ($this->hasUnknownKeyId($tokenHeader, $jwks)) {
                $jwks = $this->cachedJson($jwksUri, self::JWKS_CACHE_SECONDS, refresh: true);
            }

            [$claims, $headers] = $this->decode($logoutToken, $jwks);

            $this->validateClaims($claims, $headers, $metadata, $issuer, $clientId);
        } catch (InvalidOidcLogoutToken $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new InvalidOidcLogoutToken('The logout token is invalid.', $exception->getCode(), previous: $exception);
        }

        return [
            'subject' => $this->stringClaim($claims, 'sub'),
            'sid'     => $this->stringClaim($claims, 'sid'),
        ];
    }

    /**
     * @param  array<string, mixed>            $jwks
     * @return array{0: stdClass, 1: stdClass}
     */
    private function decode(string $logoutToken, array $jwks): array
    {
        $headers = new stdClass();
        $claims = JWT::decode($logoutToken, JWK::parseKeySet($jwks), $headers);

        return [$claims, $headers];
    }

    /** @param array<string, mixed> $metadata */
    private function validateClaims(
        stdClass $claims,
        ?stdClass $headers,
        array $metadata,
        string $issuer,
        string $clientId,
    ): void {
        if (($claims->iss ?? null) !== $issuer) {
            throw new InvalidOidcLogoutToken('The token issuer is invalid.');
        }

        $audiences = is_array($claims->aud ?? null) ? $claims->aud : [$claims->aud ?? null];
        if (!in_array($clientId, $audiences, true)) {
            throw new InvalidOidcLogoutToken('The token audience is invalid.');
        }

        $authorizedParty = $this->stringClaim($claims, 'azp');
        if ((count($audiences) > 1 && $authorizedParty !== $clientId)
            || ($authorizedParty !== null && $authorizedParty !== $clientId)) {
            throw new InvalidOidcLogoutToken('The token authorized party is invalid.');
        }

        if (!isset($claims->iat) || !is_numeric($claims->iat) || $claims->iat > time()) {
            throw new InvalidOidcLogoutToken('The token issue time is invalid.');
        }

        if (!isset($claims->exp) || !is_numeric($claims->exp)) {
            throw new InvalidOidcLogoutToken('The token has no expiry.');
        }

        if ($this->stringClaim($claims, 'jti') === null) {
            throw new InvalidOidcLogoutToken('The token has no identifier.');
        }

        if (property_exists($claims, 'nonce')) {
            throw new InvalidOidcLogoutToken('A logout token cannot contain a nonce.');
        }

        if (!isset($claims->events)
            || !$claims->events instanceof stdClass
            || !property_exists($claims->events, self::BACKCHANNEL_LOGOUT_EVENT)
            || !$claims->events->{self::BACKCHANNEL_LOGOUT_EVENT} instanceof stdClass) {
            throw new InvalidOidcLogoutToken('The logout event is invalid.');
        }

        if ($this->stringClaim($claims, 'sid') === null && $this->stringClaim($claims, 'sub') === null) {
            throw new InvalidOidcLogoutToken('The token has no session or subject identifier.');
        }

        $supportedAlgorithms = array_key_exists('id_token_signing_alg_values_supported', $metadata)
            ? $metadata['id_token_signing_alg_values_supported']
            : ['RS256'];
        if (!is_array($supportedAlgorithms)
            || !isset($headers->alg)
            || !in_array($headers->alg, $supportedAlgorithms, true)) {
            throw new InvalidOidcLogoutToken('The signing algorithm is not supported by the issuer.');
        }
    }

    /** @return array<string, mixed> */
    private function json(string $url): array
    {
        $json = $this->http
            ->acceptJson()
            ->timeout(10)
            ->get($url)
            ->throw()
            ->json();

        if (!is_array($json)) {
            throw new InvalidOidcLogoutToken('The issuer returned invalid JSON.');
        }

        return $json;
    }

    /** @return array<string, mixed> */
    private function cachedJson(string $url, int $seconds, bool $refresh = false): array
    {
        $cacheKey = 'oauth:oidc:document:'.hash('sha256', $url);
        if ($refresh) {
            $this->cache->forget($cacheKey);
        }

        return $this->cache->remember($cacheKey, $seconds, fn (): array => $this->json($url));
    }

    /** @return array<string, mixed> */
    private function assertCompactJwt(string $logoutToken): array
    {
        if (strlen($logoutToken) > self::MAX_LOGOUT_TOKEN_BYTES) {
            throw new InvalidOidcLogoutToken('The logout token is too large.');
        }

        $segments = explode('.', $logoutToken);
        if (count($segments) !== 3) {
            throw new InvalidOidcLogoutToken('The logout token is malformed.');
        }

        foreach ($segments as $segment) {
            if ($segment === '' || preg_match('/^[A-Za-z0-9_-]+$/D', $segment) !== 1) {
                throw new InvalidOidcLogoutToken('The logout token is malformed.');
            }
        }

        $header = $this->decodeJwtSegment($segments[0]);
        $this->decodeJwtSegment($segments[1]);
        if (!isset($header['alg']) || !is_string($header['alg']) || $header['alg'] === '') {
            throw new InvalidOidcLogoutToken('The logout token header is invalid.');
        }

        return $header;
    }

    /**
     * @param array<string, mixed> $header
     * @param array<string, mixed> $jwks
     */
    private function hasUnknownKeyId(array $header, array $jwks): bool
    {
        $keyId = $header['kid'] ?? null;

        if (!is_string($keyId) || $keyId === '') {
            return false;
        }

        foreach ($jwks['keys'] ?? [] as $key) {
            if (is_array($key) && ($key['kid'] ?? null) === $keyId) {
                return false;
            }
        }

        return true;
    }

    /** @return array<string, mixed> */
    private function decodeJwtSegment(string $segment): array
    {
        $padding = (4 - strlen($segment) % 4) % 4;
        $decoded = base64_decode(strtr($segment, '-_', '+/').str_repeat('=', $padding), true);
        if (!is_string($decoded)) {
            throw new InvalidOidcLogoutToken('The logout token is malformed.');
        }

        $json = json_decode($decoded, true);
        if (!is_array($json)) {
            throw new InvalidOidcLogoutToken('The logout token is malformed.');
        }

        return $json;
    }

    private function stringClaim(stdClass $claims, string $name): ?string
    {
        $value = $claims->{$name} ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}

<?php

declare(strict_types=1);

use App\Models\OAuthConnection;
use App\Services\OAuth\InvalidOidcLogoutToken;
use App\Services\OAuth\OidcLogoutTokenValidator;
use Firebase\JWT\JWT;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Cache::flush();
});

function oidcLogoutConnection(array $overrides = []): OAuthConnection
{
    return OAuthConnection::query()->create([
        'connection_id' => 'crew-sso',
        'display_name'  => 'Crew SSO',
        'provider'      => 'openidconnect',
        'client_id'     => 'phpvms-client',
        'client_secret' => 'secret',
        'configuration' => ['base_url' => 'https://issuer.example.com'],
        'enabled'       => true,
        ...$overrides,
    ]);
}

/** @return array{private: string, jwks: array<string, array<int, array<string, string>>>} */
function oidcLogoutSigningMaterial(string $algorithm = 'RS256', string $keyId = 'logout-key'): array
{
    $privateKey = openssl_pkey_new([
        'private_key_bits' => 2048,
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ]);
    expect($privateKey)->not->toBeFalse();

    $exported = openssl_pkey_export($privateKey, $privatePem);
    expect($exported)->toBeTrue();

    $details = openssl_pkey_get_details($privateKey);
    expect($details)->toBeArray();

    return [
        'private' => $privatePem,
        'jwks'    => [
            'keys' => [[
                'kty' => 'RSA',
                'kid' => $keyId,
                'use' => 'sig',
                'alg' => $algorithm,
                'n'   => oidcLogoutBase64Url($details['rsa']['n']),
                'e'   => oidcLogoutBase64Url($details['rsa']['e']),
            ]],
        ],
    ];
}

function oidcLogoutBase64Url(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

/** @return array<string, mixed> */
function oidcLogoutPayload(array $overrides = []): array
{
    return [
        'iss'    => 'https://issuer.example.com',
        'aud'    => 'phpvms-client',
        'iat'    => time(),
        'exp'    => time() + 300,
        'jti'    => 'logout-token-123',
        'sid'    => 'oidc-session',
        'events' => (object) [
            'http://schemas.openid.net/event/backchannel-logout' => (object) [],
        ],
        ...$overrides,
    ];
}

/** @param array<string, array<int, array<string, string>>> $jwks */
function fakeOidcLogoutIssuer(array $jwks, bool $includeAlgorithms = true): void
{
    $metadata = [
        'issuer'   => 'https://issuer.example.com',
        'jwks_uri' => 'https://issuer.example.com/jwks',
    ];

    if ($includeAlgorithms) {
        $metadata['id_token_signing_alg_values_supported'] = ['RS256'];
    }

    Http::fake([
        'https://issuer.example.com/.well-known/openid-configuration' => Http::response($metadata),
        'https://issuer.example.com/jwks'                             => Http::response($jwks),
    ]);
}

it('validates a signed logout token with a session identifier', function (): void {
    $material = oidcLogoutSigningMaterial();
    fakeOidcLogoutIssuer($material['jwks']);

    $token = JWT::encode(oidcLogoutPayload(), $material['private'], 'RS256', 'logout-key');
    $identifiers = app(OidcLogoutTokenValidator::class)->validate(oidcLogoutConnection(), $token);

    expect($identifiers)->toBe([
        'subject' => null,
        'sid'     => 'oidc-session',
    ]);
});

it('validates a signed logout token with only a subject', function (): void {
    $material = oidcLogoutSigningMaterial();
    fakeOidcLogoutIssuer($material['jwks']);

    $payload = oidcLogoutPayload(['sub' => 'user-123']);
    unset($payload['sid']);

    $token = JWT::encode($payload, $material['private'], 'RS256', 'logout-key');
    $identifiers = app(OidcLogoutTokenValidator::class)->validate(oidcLogoutConnection(), $token);

    expect($identifiers)->toBe([
        'subject' => 'user-123',
        'sid'     => null,
    ]);
});

it('rejects malformed and oversized tokens before contacting the issuer', function (string $token): void {
    Http::fake();

    expect(fn () => app(OidcLogoutTokenValidator::class)->validate(oidcLogoutConnection(), $token))
        ->toThrow(InvalidOidcLogoutToken::class);
    Http::assertNothingSent();
})->with([
    'missing segments'    => ['not-a-jwt'],
    'invalid base64url'   => ['e30=.e30.signature'],
    'invalid header json' => [oidcLogoutBase64Url('not-json').'.'.oidcLogoutBase64Url('{}').'.signature'],
    'oversized token'     => [str_repeat('a', 32769)],
]);

it('reuses cached discovery and JWKS documents', function (): void {
    $material = oidcLogoutSigningMaterial();
    fakeOidcLogoutIssuer($material['jwks']);
    $connection = oidcLogoutConnection();
    $validator = app(OidcLogoutTokenValidator::class);

    $validator->validate(
        $connection,
        JWT::encode(oidcLogoutPayload(['jti' => 'first']), $material['private'], 'RS256', 'logout-key'),
    );
    $validator->validate(
        $connection,
        JWT::encode(oidcLogoutPayload(['jti' => 'second']), $material['private'], 'RS256', 'logout-key'),
    );

    Http::assertSentCount(2);
});

it('refreshes cached JWKS when the issuer rotates signing keys', function (): void {
    $oldMaterial = oidcLogoutSigningMaterial(keyId: 'old-key');
    $newMaterial = oidcLogoutSigningMaterial(keyId: 'new-key');
    $jwksRequests = 0;
    Http::fake(function (ClientRequest $request) use ($oldMaterial, $newMaterial, &$jwksRequests) {
        if ($request->url() === 'https://issuer.example.com/.well-known/openid-configuration') {
            return Http::response([
                'issuer'                                => 'https://issuer.example.com',
                'jwks_uri'                              => 'https://issuer.example.com/jwks',
                'id_token_signing_alg_values_supported' => ['RS256'],
            ]);
        }

        $jwksRequests++;

        return Http::response($jwksRequests === 1 ? $oldMaterial['jwks'] : $newMaterial['jwks']);
    });
    $connection = oidcLogoutConnection();
    $validator = app(OidcLogoutTokenValidator::class);

    $validator->validate(
        $connection,
        JWT::encode(oidcLogoutPayload(['jti' => 'old']), $oldMaterial['private'], 'RS256', 'old-key'),
    );
    $identifiers = $validator->validate(
        $connection,
        JWT::encode(oidcLogoutPayload(['jti' => 'new']), $newMaterial['private'], 'RS256', 'new-key'),
    );

    expect($identifiers['sid'])->toBe('oidc-session')
        ->and($jwksRequests)->toBe(2);
    Http::assertSentCount(3);
});

it('rejects invalid logout token claims', function (array $overrides): void {
    $material = oidcLogoutSigningMaterial();
    fakeOidcLogoutIssuer($material['jwks']);

    $token = JWT::encode(oidcLogoutPayload($overrides), $material['private'], 'RS256', 'logout-key');

    expect(fn () => app(OidcLogoutTokenValidator::class)->validate(oidcLogoutConnection(), $token))
        ->toThrow(InvalidOidcLogoutToken::class);
    Http::assertSentCount(2);
})->with([
    'wrong issuer'        => [['iss' => 'https://other.example.com']],
    'wrong audience'      => [['aud' => 'other-client']],
    'expired token'       => [['exp' => time() - 60]],
    'invalid events'      => [['events' => (object) ['other-event' => (object) []]]],
    'invalid event value' => [['events' => (object) [
        'http://schemas.openid.net/event/backchannel-logout' => true,
    ]]],
    'forbidden nonce' => [['nonce' => 'browser-nonce']],
]);

it('rejects missing and invalid required token identifiers and times', function (
    string $claim,
    mixed $value,
    bool $remove,
): void {
    $material = oidcLogoutSigningMaterial();
    fakeOidcLogoutIssuer($material['jwks']);
    $payload = oidcLogoutPayload();

    if ($remove) {
        unset($payload[$claim]);
    } else {
        $payload[$claim] = $value;
    }

    $token = JWT::encode($payload, $material['private'], 'RS256', 'logout-key');

    expect(fn () => app(OidcLogoutTokenValidator::class)->validate(oidcLogoutConnection(), $token))
        ->toThrow(InvalidOidcLogoutToken::class);
})->with([
    'missing iat'     => ['iat', null, true],
    'non-numeric iat' => ['iat', 'now', false],
    'future iat'      => ['iat', time() + 60, false],
    'missing jti'     => ['jti', null, true],
    'empty jti'       => ['jti', '', false],
]);

it('uses RS256 when issuer metadata omits supported algorithms', function (): void {
    $material = oidcLogoutSigningMaterial();
    fakeOidcLogoutIssuer($material['jwks'], includeAlgorithms: false);

    $token = JWT::encode(oidcLogoutPayload(), $material['private'], 'RS256', 'logout-key');

    expect(app(OidcLogoutTokenValidator::class)->validate(oidcLogoutConnection(), $token)['sid'])
        ->toBe('oidc-session');
});

it('rejects a non-RS256 token when issuer metadata omits supported algorithms', function (): void {
    $material = oidcLogoutSigningMaterial('RS384');
    fakeOidcLogoutIssuer($material['jwks'], includeAlgorithms: false);

    $token = JWT::encode(oidcLogoutPayload(), $material['private'], 'RS384', 'logout-key');

    expect(fn () => app(OidcLogoutTokenValidator::class)->validate(oidcLogoutConnection(), $token))
        ->toThrow(InvalidOidcLogoutToken::class);
});

it('validates the authorized party for multiple audiences', function (): void {
    $material = oidcLogoutSigningMaterial();
    fakeOidcLogoutIssuer($material['jwks']);
    $token = JWT::encode(oidcLogoutPayload([
        'aud' => ['phpvms-client', 'accounting-client'],
        'azp' => 'phpvms-client',
    ]), $material['private'], 'RS256', 'logout-key');

    expect(app(OidcLogoutTokenValidator::class)->validate(oidcLogoutConnection(), $token)['sid'])
        ->toBe('oidc-session');
});

it('rejects multiple audiences without the matching authorized party', function (?string $authorizedParty): void {
    $material = oidcLogoutSigningMaterial();
    fakeOidcLogoutIssuer($material['jwks']);
    $payload = oidcLogoutPayload(['aud' => ['phpvms-client', 'accounting-client']]);

    if ($authorizedParty !== null) {
        $payload['azp'] = $authorizedParty;
    }

    $token = JWT::encode($payload, $material['private'], 'RS256', 'logout-key');

    expect(fn () => app(OidcLogoutTokenValidator::class)->validate(oidcLogoutConnection(), $token))
        ->toThrow(InvalidOidcLogoutToken::class);
})->with([
    'missing azp' => [null],
    'wrong azp'   => ['accounting-client'],
]);

it('rejects a token signed by another key', function (): void {
    $trusted = oidcLogoutSigningMaterial();
    $untrusted = oidcLogoutSigningMaterial();
    fakeOidcLogoutIssuer($trusted['jwks']);

    $token = JWT::encode(oidcLogoutPayload(), $untrusted['private'], 'RS256', 'logout-key');

    expect(fn () => app(OidcLogoutTokenValidator::class)->validate(oidcLogoutConnection(), $token))
        ->toThrow(InvalidOidcLogoutToken::class);
    Http::assertSentCount(2);
});

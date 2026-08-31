<?php

declare(strict_types=1);

use App\Features\OAuth\Helpers\OAuthConnectionService;
use App\Features\OAuth\Helpers\SocialiteProviderRegistry;
use App\Models\OAuthConnection;
use App\Models\User;
use App\Models\UserIdentity;
use App\Models\UserOAuthToken;
use Illuminate\Validation\ValidationException;

function makeOAuthConnectionService(bool $packagesInstalled = true): OAuthConnectionService
{
    return new OAuthConnectionService(new SocialiteProviderRegistry(
        static fn (): bool => $packagesInstalled,
    ));
}

function validOpenIdConnection(array $overrides = []): array
{
    return [
        'connection_id' => 'crew-sso',
        'display_name'  => 'Crew SSO',
        'provider'      => 'openidconnect',
        'client_id'     => 'client-id',
        'client_secret' => 'client-secret',
        'scopes'        => ['openid', 'profile', 'email'],
        'base_url'      => 'https://auth.example.com',
        'enabled'       => true,
        'login_enabled' => true,
        ...$overrides,
    ];
}

it('validates connection IDs and provider fields', function (): void {
    $service = makeOAuthConnectionService();

    expect(fn (): OAuthConnection => $service->create(validOpenIdConnection([
        'connection_id' => 'Crew SSO',
    ])))->toThrow(ValidationException::class);

    expect(fn (): OAuthConnection => $service->create(validOpenIdConnection([
        'connection_id' => 'missing-issuer',
        'base_url'      => null,
    ])))->toThrow(ValidationException::class);
});

it('allows isolated OpenID Connect connections', function (): void {
    $service = makeOAuthConnectionService();
    $first = $service->create(validOpenIdConnection([
        'logo_url' => 'https://cdn.example.com/crew-sso.png',
    ]));
    $second = $service->create(validOpenIdConnection([
        'connection_id' => 'staff-sso',
        'display_name'  => 'Staff SSO',
        'client_id'     => 'staff-client',
        'base_url'      => 'https://staff-auth.example.com',
    ]));

    $service->loadRuntimeConfiguration();

    expect($service->driverFor($first))->toBe('oidc_crew-sso')
        ->and($service->driverFor($second))->toBe('oidc_staff-sso')
        ->and(config('oidc.connections.crew-sso.client_id'))->toBe('client-id')
        ->and(config('oidc.connections.staff-sso.client_id'))->toBe('staff-client')
        ->and(config('services.oidc_crew-sso.base_url'))->toBe('https://auth.example.com')
        ->and(config('services.oidc_crew-sso.logo_url'))->toBeNull()
        ->and($first->configuration['logo_url'])->toBe('https://cdn.example.com/crew-sso.png')
        ->and(config('services.oidc_staff-sso.base_url'))->toBe('https://staff-auth.example.com');
});

it('rejects disabled connections during runtime resolution', function (): void {
    $service = makeOAuthConnectionService();
    $service->create(validOpenIdConnection([
        'connection_id' => 'disabled-sso',
        'enabled'       => false,
    ]));

    expect(fn (): OAuthConnection => $service->resolve('disabled-sso'))->toThrow(
        ValidationException::class,
        'not enabled',
    );
});

it('registers an enabled fixed provider during runtime resolution', function (): void {
    $service = new OAuthConnectionService(new SocialiteProviderRegistry());
    $connection = OAuthConnection::query()->where('connection_id', 'discord')->firstOrFail();
    $service->update($connection, [
        'client_id'     => 'discord-client',
        'client_secret' => 'discord-secret',
        'enabled'       => true,
    ]);

    expect($service->resolve('discord')->connection_id)->toBe('discord')
        ->and(config('services.discord.client_id'))->toBe('discord-client')
        ->and(config('services.discord.scopes'))->toBe(['identify']);
});

it('preserves configured VATSIM scopes while enforcing email', function (): void {
    $service = new OAuthConnectionService(new SocialiteProviderRegistry());
    $connection = OAuthConnection::query()->where('connection_id', 'vatsim')->firstOrFail();
    $updated = $service->update($connection, [
        'client_id'     => 'vatsim-client',
        'client_secret' => 'vatsim-secret',
        'scopes'        => ['pilotread', 'email', 'pilotread'],
        'enabled'       => true,
    ]);

    $service->resolve('vatsim');

    expect($updated->scopes)->toBe(['email', 'pilotread'])
        ->and(config('services.vatsim.scopes'))->toBe(['email', 'pilotread']);
});

it('enforces openid for generic OpenID Connect connections', function (): void {
    $connection = makeOAuthConnectionService()->create(validOpenIdConnection([
        'scopes' => ['profile'],
    ]));

    expect($connection->scopes)->toBe(['openid', 'profile']);
});

it('uses the standard generic OpenID Connect scopes by default', function (): void {
    $connection = makeOAuthConnectionService()->create(validOpenIdConnection([
        'scopes' => [],
    ]));

    expect($connection->scopes)->toBe(['openid', 'email', 'profile']);
});

it('accepts a client ID up to the schema limit', function (): void {
    $clientId = str_repeat('a', 2048);
    $connection = makeOAuthConnectionService()->create(validOpenIdConnection([
        'client_id' => $clientId,
    ]));

    expect($connection->client_id)->toBe($clientId);
});

it('uses exactly the permitted vacentral identity scopes', function (): void {
    $connection = makeOAuthConnectionService()->create(validOpenIdConnection([
        'connection_id' => 'vacentral',
        'display_name'  => 'vacentral',
        'provider'      => 'vacentral',
        'scopes'        => ['airlines:read', 'email'],
    ]));

    expect($connection->scopes)->toBe(['openid', 'profile', 'email']);
});

it('keeps a saved secret when an edit submits a blank value', function (): void {
    $service = makeOAuthConnectionService();
    $connection = $service->create(validOpenIdConnection());

    $updated = $service->update($connection, [
        'display_name'  => 'Updated SSO',
        'client_secret' => '',
    ]);

    expect($updated->display_name)->toBe('Updated SSO')
        ->and($updated->client_secret)->toBe('client-secret');
});

it('does not allow changing a connection ID', function (): void {
    $service = makeOAuthConnectionService();
    $connection = $service->create(validOpenIdConnection());

    expect(fn (): OAuthConnection => $service->update($connection, [
        'connection_id' => 'renamed-sso',
    ]))->toThrow(ValidationException::class, 'cannot be changed');
});

it('protects linked identity trust boundaries while allowing credential rotation', function (): void {
    $service = makeOAuthConnectionService();
    $connection = $service->create(validOpenIdConnection());
    $user = User::factory()->create();
    UserIdentity::query()->create([
        'user_id'          => $user->id,
        'connection_id'    => $connection->connection_id,
        'provider_user_id' => 'external-user',
    ]);

    expect(fn (): OAuthConnection => $service->update($connection, [
        'provider' => 'vacentral',
    ]))->toThrow(ValidationException::class, 'provider cannot be changed')
        ->and(fn (): OAuthConnection => $service->update($connection, [
            'base_url' => 'https://replacement.example.com',
        ]))->toThrow(ValidationException::class, 'issuer URL cannot be changed');

    $updated = $service->update($connection, [
        'client_id'     => 'rotated-client',
        'client_secret' => 'rotated-secret',
    ]);

    expect($updated->client_id)->toBe('rotated-client')
        ->and($updated->client_secret)->toBe('rotated-secret');
});

it('protects addon-managed connection settings', function (): void {
    $service = makeOAuthConnectionService();
    $connection = $service->createManaged(validOpenIdConnection(), 'vacentral');

    expect(fn (): OAuthConnection => $service->update($connection, [
        'client_id' => 'replacement-client',
    ]))->toThrow(ValidationException::class, 'managing addon')
        ->and(fn () => $service->delete($connection))->toThrow(
            ValidationException::class,
            'must be removed by',
        );

    expect($service->disable($connection)->enabled)->toBeFalse();
});

it('reports unavailable provider packages before saving', function (): void {
    $service = makeOAuthConnectionService(false);

    expect(fn (): OAuthConnection => $service->create(validOpenIdConnection()))->toThrow(
        ValidationException::class,
        'socialiteproviders/openidconnect',
    );
});

it('filters enabled connections by public surface', function (): void {
    $service = makeOAuthConnectionService();
    $service->create(validOpenIdConnection([
        'connection_id'        => 'login-sso',
        'registration_enabled' => false,
        'linking_enabled'      => true,
    ]));
    $service->create(validOpenIdConnection([
        'connection_id'        => 'registration-sso',
        'login_enabled'        => false,
        'registration_enabled' => true,
    ]));

    expect($service->enabledFor('login')->pluck('connection_id')->all())->toContain('login-sso')
        ->and($service->enabledFor('registration')->pluck('connection_id')->all())->toContain('registration-sso')
        ->and($service->enabledFor('linking')->pluck('connection_id')->all())->toContain('login-sso');
});

it('omits enabled connections whose provider package is unavailable', function (): void {
    makeOAuthConnectionService()->create(validOpenIdConnection());

    expect(makeOAuthConnectionService(false)->enabledFor('login')->pluck('connection_id')->all())
        ->not->toContain('crew-sso');
});

it('does not restore deleted database connections from legacy config', function (): void {
    config(['services.discord.enabled' => true]);
    $service = makeOAuthConnectionService();
    $connection = OAuthConnection::query()->where('connection_id', 'discord')->firstOrFail();

    $service->delete($connection);

    expect($service->find('discord'))->toBeNull();
});

it('deletes connection token rows with the connection', function (): void {
    $service = makeOAuthConnectionService();
    $connection = $service->create(validOpenIdConnection());
    $user = User::factory()->create();
    UserOAuthToken::query()->create([
        'user_id'       => $user->id,
        'connection_id' => $connection->connection_id,
        'token'         => 'token',
        'refresh_token' => 'refresh-token',
    ]);

    $service->delete($connection);

    expect(UserOAuthToken::query()->where('connection_id', 'crew-sso')->exists())->toBeFalse()
        ->and(OAuthConnection::query()->where('connection_id', 'crew-sso')->exists())->toBeFalse();
});

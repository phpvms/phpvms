<?php

declare(strict_types=1);

use App\Features\OAuth\Helpers\SocialiteProviderRegistry;
use SocialiteProviders\Discord\Provider;

it('reports installed and unavailable provider packages', function (): void {
    $registry = new SocialiteProviderRegistry(
        static fn (string $class): bool => $class === Provider::class,
    );

    expect($registry->isInstalled('discord'))->toBeTrue()
        ->and($registry->isInstalled('vacentral'))->toBeFalse()
        ->and($registry->isInstalled('openidconnect'))->toBeFalse()
        ->and($registry->unavailableMessage('discord'))->toBeNull()
        ->and($registry->unavailableMessage('openidconnect'))
        ->toContain('socialiteproviders/openidconnect');
});

it('exposes stable provider and field definitions', function (): void {
    $registry = new SocialiteProviderRegistry(static fn (): bool => true);
    $openId = $registry->find('openidconnect');

    expect($openId)->not->toBeNull()
        ->and($openId)->toMatchArray([
            'key'       => 'openidconnect',
            'label'     => 'OpenID Connect',
            'package'   => 'socialiteproviders/openidconnect',
            'multiple'  => true,
            'installed' => true,
        ])
        ->and(collect($openId['fields'])->pluck('key')->all())
        ->toBe(['client_id', 'client_secret', 'scopes', 'base_url', 'logo_url']);

    foreach ($openId['fields'] as $field) {
        expect($field)->toHaveKeys(['key', 'label', 'type', 'required']);
    }

    $vacentral = $registry->find('vacentral');
    expect($vacentral)->toMatchArray([
        'package'   => 'socialiteproviders/vacentral',
        'multiple'  => false,
        'installed' => true,
    ])->and(collect($vacentral['fields'])->firstWhere('key', 'scopes')['default'])
        ->toBe(['openid', 'profile', 'email']);
});

it('uses fixed drivers and connection-specific OIDC drivers', function (): void {
    $registry = new SocialiteProviderRegistry(static fn (): bool => true);

    expect($registry->driverFor('discord', 'crew-discord'))->toBe('discord')
        ->and($registry->driverFor('openidconnect', 'staff-sso'))->toBe('oidc_staff-sso')
        ->and($registry->driverFor('openidconnect', 'pilot-sso'))->toBe('oidc_pilot-sso');
});

it('declares required compatibility scopes for fixed providers', function (): void {
    $registry = new SocialiteProviderRegistry(static fn (): bool => true);

    expect($registry->requiredScopes('discord'))->toBe(['identify'])
        ->and($registry->requiredScopes('vatsim'))->toBe(['email'])
        ->and($registry->requiredScopes('vacentral'))->toBe(['openid', 'profile', 'email'])
        ->and($registry->requiredScopes('openidconnect'))->toBe(['openid'])
        ->and(collect($registry->find('discord')['fields'])->firstWhere('key', 'scopes')['default'])
        ->toBe(['identify'])
        ->and(collect($registry->find('vatsim')['fields'])->firstWhere('key', 'scopes')['default'])
        ->toBe(['email']);
});

it('reports providers outside the registry', function (): void {
    $registry = new SocialiteProviderRegistry(static fn (): bool => true);

    expect($registry->find('missing'))->toBeNull()
        ->and($registry->unavailableMessage('missing'))->toBe('Unknown social login provider [missing].');
});

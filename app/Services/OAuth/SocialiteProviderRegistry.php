<?php

namespace App\Services\OAuth;

use Closure;
use SocialiteProviders\Discord\Provider;

final readonly class SocialiteProviderRegistry
{
    private Closure $classExists;

    public function __construct(?Closure $classExists = null)
    {
        $this->classExists = $classExists ?? class_exists(...);
    }

    /**
     * @return array<string, array{
     *     key: string,
     *     label: string,
     *     package: string,
     *     providerClass: string,
     *     protocol: string,
     *     multiple: bool,
     *     requiredScopes: list<string>,
     *     installed: bool,
     *     fields: list<array{
     *         key: string,
     *         label: string,
     *         type: 'text'|'password'|'url'|'tags',
     *         required: bool,
     *         default?: mixed,
     *         placeholder?: string,
     *         helperText?: string,
     *         rules?: list<string>
     *     }>
     * }>
     */
    public function all(): array
    {
        $providers = $this->definitions();

        foreach ($providers as &$provider) {
            $provider['installed'] = ($this->classExists)($provider['providerClass']);
        }

        return $providers;
    }

    /**
     * @return null|array{
     *     key: string,
     *     label: string,
     *     package: string,
     *     providerClass: string,
     *     protocol: string,
     *     multiple: bool,
     *     requiredScopes: list<string>,
     *     installed: bool,
     *     fields: list<array<string, mixed>>
     * }
     */
    public function find(string $provider): ?array
    {
        return $this->all()[strtolower($provider)] ?? null;
    }

    public function isInstalled(string $provider): bool
    {
        return $this->find($provider)['installed'] ?? false;
    }

    public function protocolFor(string $provider): ?string
    {
        return $this->find($provider)['protocol'] ?? null;
    }

    public function driverFor(string $provider, string $connectionId): string
    {
        return strtolower($provider) === 'openidconnect'
            ? 'oidc_'.$connectionId
            : strtolower($provider);
    }

    /**
     * @return list<string>
     */
    public function requiredScopes(string $provider): array
    {
        return $this->find($provider)['requiredScopes'] ?? [];
    }

    public function unavailableMessage(string $provider): ?string
    {
        $definition = $this->find($provider);

        if ($definition === null) {
            return 'Unknown social login provider ['.$provider.'].';
        }

        if ($definition['installed']) {
            return null;
        }

        return 'Social login provider ['.$provider.'] requires the Composer package ['.$definition['package'].'].';
    }

    /**
     * @return array<string, array{
     *     key: string,
     *     label: string,
     *     package: string,
     *     providerClass: string,
     *     protocol: string,
     *     multiple: bool,
     *     requiredScopes: list<string>,
     *     fields: list<array<string, mixed>>
     * }>
     */
    private function definitions(): array
    {
        $credentials = [
            [
                'key'      => 'client_id',
                'label'    => 'Client ID',
                'type'     => 'text',
                'required' => true,
                'rules'    => ['string', 'max:2048'],
            ],
            [
                'key'        => 'client_secret',
                'label'      => 'Client Secret',
                'type'       => 'password',
                'required'   => true,
                'helperText' => 'Leave blank when editing to keep the saved secret.',
                'rules'      => ['string', 'max:8192'],
            ],
            [
                'key'      => 'scopes',
                'label'    => 'Scopes',
                'type'     => 'tags',
                'required' => false,
                'default'  => [],
                'rules'    => ['array'],
            ],
        ];
        $credentialsWithScopes = static fn (array $scopes): array => [
            $credentials[0],
            $credentials[1],
            [...$credentials[2], 'default' => $scopes],
        ];
        $oidcCredentials = $credentialsWithScopes(['openid', 'profile', 'email']);
        $logo = [
            'key'        => 'logo_url',
            'label'      => 'Logo URL',
            'type'       => 'url',
            'required'   => false,
            'helperText' => 'Shown beside this provider on login and registration buttons.',
            'rules'      => ['url:http,https', 'max:2048'],
        ];
        $issuer = [
            'key'         => 'base_url',
            'label'       => 'Issuer URL',
            'type'        => 'url',
            'required'    => true,
            'placeholder' => 'https://auth.example.com',
            'helperText'  => 'The issuer must publish an OpenID Connect discovery document.',
            'rules'       => ['url:http,https', 'max:2048'],
        ];

        return [
            'discord' => [
                'key'            => 'discord',
                'label'          => 'Discord',
                'package'        => 'socialiteproviders/discord',
                'providerClass'  => Provider::class,
                'protocol'       => 'oauth2',
                'multiple'       => false,
                'requiredScopes' => ['identify'],
                'fields'         => [...$credentialsWithScopes(['identify']), $logo],
            ],
            'vatsim' => [
                'key'            => 'vatsim',
                'label'          => 'VATSIM',
                'package'        => 'socialiteproviders/vatsim',
                'providerClass'  => \SocialiteProviders\Vatsim\Provider::class,
                'protocol'       => 'oauth2',
                'multiple'       => false,
                'requiredScopes' => ['email'],
                'fields'         => [...$credentialsWithScopes(['email']), $logo],
            ],
            'ivao' => [
                'key'            => 'ivao',
                'label'          => 'IVAO',
                'package'        => 'socialiteproviders/ivao',
                'providerClass'  => \SocialiteProviders\Ivao\Provider::class,
                'protocol'       => 'oauth2',
                'multiple'       => false,
                'requiredScopes' => [],
                'fields'         => [...$credentials, $logo],
            ],
            'vacentral' => [
                'key'            => 'vacentral',
                'label'          => 'vacentral',
                'package'        => 'socialiteproviders/vacentral',
                'providerClass'  => 'SocialiteProviders\\VaCentral\\Provider',
                'protocol'       => 'oidc',
                'multiple'       => false,
                'requiredScopes' => ['openid', 'profile', 'email'],
                'fields'         => [
                    ...$oidcCredentials,
                    [
                        ...$issuer,
                        'placeholder' => 'https://auth.vacentral.net',
                        'default'     => 'https://auth.vacentral.net',
                    ],
                    $logo,
                ],
            ],
            'openidconnect' => [
                'key'            => 'openidconnect',
                'label'          => 'OpenID Connect',
                'package'        => 'socialiteproviders/openidconnect',
                'providerClass'  => \SocialiteProviders\OpenIDConnect\Provider::class,
                'protocol'       => 'oidc',
                'multiple'       => true,
                'requiredScopes' => ['openid'],
                'fields'         => [
                    ...$oidcCredentials,
                    $issuer,
                    $logo,
                ],
            ],
        ];
    }
}

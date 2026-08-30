<?php

namespace App\Services\OAuth;

use App\Models\OAuthConnection;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use SocialiteProviders\Manager\SocialiteWasCalled;
use Throwable;

final readonly class OAuthConnectionService
{
    private const array SURFACE_COLUMNS = [
        'login'        => 'login_enabled',
        'registration' => 'registration_enabled',
        'linking'      => 'linking_enabled',
    ];

    public function __construct(private SocialiteProviderRegistry $registry) {}

    /**
     * @return Collection<int, OAuthConnection>
     */
    public function all(): Collection
    {
        $connections = $this->tableAvailable()
            ? $this->persistedConnections()
            : $this->legacyConnections();

        return $connections->sortBy([
            ['sort_order', 'asc'],
            ['display_name', 'asc'],
        ])->values();
    }

    /**
     * @return Collection<int, OAuthConnection>
     */
    public function enabledFor(string $surface): Collection
    {
        $column = self::SURFACE_COLUMNS[$surface] ?? null;
        if ($column === null) {
            throw ValidationException::withMessages([
                'surface' => 'Unknown social login surface ['.$surface.'].',
            ]);
        }

        return $this->all()->filter(
            fn (OAuthConnection $connection): bool => $connection->enabled
                && (bool) $connection->{$column}
                && $this->registry->isInstalled($connection->provider),
        )->values();
    }

    public function find(string $connectionId): ?OAuthConnection
    {
        if ($this->tableAvailable()) {
            return OAuthConnection::query()->where('connection_id', $connectionId)->first();
        }

        return $this->legacyConnections()->firstWhere('connection_id', $connectionId);
    }

    /**
     * Resolve an enabled connection and make its configuration available to Socialite.
     *
     * @throws ValidationException
     */
    public function resolve(string $connectionId): OAuthConnection
    {
        $connection = $this->find($connectionId);

        if (!$connection instanceof OAuthConnection || !$connection->enabled) {
            throw ValidationException::withMessages([
                'connection_id' => 'Social login connection ['.$connectionId.'] is not enabled.',
            ]);
        }

        $this->assertProviderAvailable($connection->provider);
        $this->configure($connection);
        $this->registerRuntimeProvider($connection);

        return $connection;
    }

    public function driverFor(OAuthConnection $connection): string
    {
        return $this->registry->driverFor($connection->provider, $connection->connection_id);
    }

    /** @param array<string, bool|int|string|null> $pending */
    public function pendingIdentityMatches(OAuthConnection $connection, array $pending): bool
    {
        return (string) ($pending['connection_record_id'] ?? '') === (string) $connection->getKey()
            && ($pending['provider'] ?? null) === $connection->provider
            && $this->normalizeIssuer($pending['issuer'] ?? null)
                === $this->normalizeIssuer($connection->configuration['base_url'] ?? null);
    }

    public function assertServerSideSessionRevocationSupported(OAuthConnection $connection): void
    {
        if (in_array($connection->provider, ['openidconnect', 'vacentral'], true)
            && config('session.driver') === 'cookie') {
            throw ValidationException::withMessages([
                'session_driver' => 'OpenID Connect social login requires a server-side session driver; SESSION_DRIVER=cookie cannot be revoked.',
            ]);
        }
    }

    public function create(array $attributes): OAuthConnection
    {
        unset($attributes['managed_by']);

        return $this->persist($attributes);
    }

    public function createManaged(array $attributes, string $manager): OAuthConnection
    {
        $attributes['managed_by'] = $manager;

        return $this->persist($attributes);
    }

    public function update(OAuthConnection $connection, array $attributes): OAuthConnection
    {
        if ($connection->managed_by !== null) {
            $this->assertManagedUpdateIsSafe($attributes);
        }

        return $this->persist($attributes, $connection);
    }

    public function updateManaged(OAuthConnection $connection, array $attributes, string $manager): OAuthConnection
    {
        if ($connection->managed_by !== $manager) {
            throw ValidationException::withMessages([
                'managed_by' => 'This connection is managed by ['.$connection->managed_by.'].',
            ]);
        }

        $attributes['managed_by'] = $manager;

        return $this->persist($attributes, $connection);
    }

    public function enable(OAuthConnection $connection): OAuthConnection
    {
        return $this->update($connection, ['enabled' => true]);
    }

    public function disable(OAuthConnection $connection): OAuthConnection
    {
        return $this->update($connection, ['enabled' => false]);
    }

    public function delete(OAuthConnection $connection): void
    {
        if ($connection->managed_by !== null) {
            throw ValidationException::withMessages([
                'connection_id' => 'Managed social login connections must be removed by ['.$connection->managed_by.'].',
            ]);
        }

        DB::transaction(function () use ($connection): void {
            app(ExternalAuthSessionService::class)->revokeAll($connection);
            $connection->tokens()->delete();
            $connection->delete();
        });
    }

    /**
     * Load exact per-driver configuration before Socialite providers are extended.
     */
    public function loadRuntimeConfiguration(): void
    {
        foreach ($this->all() as $connection) {
            if ($connection->enabled && $this->registry->isInstalled($connection->provider)) {
                $this->configure($connection);
            }
        }
    }

    /**
     * @return array<string, string>
     */
    public function providerClasses(): array
    {
        $providers = [];

        foreach ($this->registry->all() as $definition) {
            if ($definition['installed'] && !$definition['multiple']) {
                $providers[$definition['key']] = $definition['providerClass'];
            }
        }

        foreach ($this->all() as $connection) {
            $definition = $this->registry->find($connection->provider);
            if (!$connection->enabled || $definition === null || !$definition['installed'] || !$definition['multiple']) {
                continue;
            }

            $providers[$this->driverFor($connection)] = $definition['providerClass'];
        }

        return $providers;
    }

    private function configure(OAuthConnection $connection): void
    {
        $driver = $this->driverFor($connection);
        $configuration = [
            ...($connection->provider === 'openidconnect' ? [] : (array) config('services.'.$connection->provider, [])),
            ...Arr::except((array) $connection->configuration, ['logo_url']),
            'client_id'     => $connection->client_id,
            'client_secret' => $connection->client_secret,
            'redirect'      => url('/oauth/'.$connection->connection_id.'/callback'),
            'scopes'        => $this->normalizeScopes($connection->provider, $connection->scopes),
        ];

        config(['services.'.$driver => $configuration]);

        if ($connection->provider === 'openidconnect') {
            config(['oidc.connections.'.$connection->connection_id => $configuration]);
        }
    }

    private function registerRuntimeProvider(OAuthConnection $connection): void
    {
        $definition = $this->registry->find($connection->provider);
        if ($definition === null || !$definition['installed']) {
            return;
        }

        app(SocialiteWasCalled::class)->extendSocialite(
            $this->driverFor($connection),
            $definition['providerClass'],
        );
    }

    private function persist(array $attributes, ?OAuthConnection $connection = null): OAuthConnection
    {
        if ($connection instanceof OAuthConnection) {
            return DB::transaction(function () use ($attributes, $connection): OAuthConnection {
                $lockedConnection = OAuthConnection::query()
                    ->lockForUpdate()
                    ->findOrFail($connection->getKey());

                return $this->persistAttributes($attributes, $lockedConnection);
            });
        }

        return $this->persistAttributes($attributes);
    }

    private function persistAttributes(array $attributes, ?OAuthConnection $connection = null): OAuthConnection
    {
        $normalized = $this->normalize($attributes, $connection);
        $this->assertIdentityTrustBoundaryUnchanged($connection, $normalized);
        $validated = Validator::make(
            $normalized,
            $this->rules($normalized, $connection),
        )->validate();

        $this->assertProviderAvailable($validated['provider']);
        $this->assertProviderMultiplicity($validated['provider'], $connection);

        $connection ??= new OAuthConnection();
        $connection->fill($validated);
        $connection->save();

        return $connection->refresh();
    }

    private function normalize(array $attributes, ?OAuthConnection $connection): array
    {
        $base = $connection?->only([
            'connection_id',
            'display_name',
            'provider',
            'client_id',
            'client_secret',
            'scopes',
            'configuration',
            'enabled',
            'login_enabled',
            'registration_enabled',
            'linking_enabled',
            'managed_by',
            'sort_order',
        ]) ?? [
            'enabled'              => false,
            'login_enabled'        => false,
            'registration_enabled' => false,
            'linking_enabled'      => false,
            'sort_order'           => 0,
            'scopes'               => [],
            'configuration'        => [],
        ];

        if ($connection instanceof OAuthConnection
            && array_key_exists('connection_id', $attributes)
            && $attributes['connection_id'] !== $connection->connection_id) {
            throw ValidationException::withMessages([
                'connection_id' => 'The connection ID cannot be changed.',
            ]);
        }

        if ($connection instanceof OAuthConnection && ($attributes['client_secret'] ?? null) === '') {
            unset($attributes['client_secret']);
        }

        $normalized = [...$base, ...$attributes];
        $normalized['provider'] = strtolower((string) ($normalized['provider'] ?? ''));
        $normalized['scopes'] = $this->normalizeScopes($normalized['provider'], $normalized['scopes'] ?? []);
        $normalized['configuration'] = (array) ($normalized['configuration'] ?? []);

        $definition = $this->registry->find($normalized['provider']);
        foreach ($definition['fields'] ?? [] as $field) {
            $key = (string) $field['key'];
            if (in_array($key, ['client_id', 'client_secret', 'scopes'], true)) {
                continue;
            }

            if (array_key_exists($key, $attributes)) {
                $normalized['configuration'][$key] = $attributes[$key];
                unset($normalized[$key]);
            }
        }

        return Arr::only($normalized, [
            'connection_id',
            'display_name',
            'provider',
            'client_id',
            'client_secret',
            'scopes',
            'configuration',
            'enabled',
            'login_enabled',
            'registration_enabled',
            'linking_enabled',
            'managed_by',
            'sort_order',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(array $attributes, ?OAuthConnection $connection): array
    {
        $connectionIdRule = Rule::unique('oauth_connections', 'connection_id');
        if ($connection instanceof OAuthConnection) {
            $connectionIdRule->ignore($connection->id);
        }

        $rules = [
            'connection_id'        => ['required', 'string', 'max:64', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $connectionIdRule],
            'display_name'         => ['required', 'string', 'max:255'],
            'provider'             => ['required', 'string', Rule::in(array_keys($this->registry->all()))],
            'client_id'            => ['required', 'string', 'max:2048'],
            'client_secret'        => [$connection instanceof OAuthConnection ? 'nullable' : 'required', 'string', 'max:8192'],
            'scopes'               => ['nullable', 'array'],
            'scopes.*'             => ['string', 'max:255', 'distinct'],
            'configuration'        => ['nullable', 'array'],
            'enabled'              => ['boolean'],
            'login_enabled'        => ['boolean'],
            'registration_enabled' => ['boolean'],
            'linking_enabled'      => ['boolean'],
            'managed_by'           => ['nullable', 'string', 'max:64'],
            'sort_order'           => ['integer', 'min:0'],
        ];

        $definition = $this->registry->find((string) ($attributes['provider'] ?? ''));
        foreach ($definition['fields'] ?? [] as $field) {
            $key = (string) $field['key'];
            if (in_array($key, ['client_id', 'client_secret', 'scopes'], true)) {
                continue;
            }

            $rules['configuration.'.$key] = [
                $field['required'] ? 'required' : 'nullable',
                ...($field['rules'] ?? []),
            ];
        }

        return $rules;
    }

    private function assertProviderAvailable(string $provider): void
    {
        $message = $this->registry->unavailableMessage($provider);
        if ($message !== null) {
            throw ValidationException::withMessages(['provider' => $message]);
        }
    }

    private function assertProviderMultiplicity(string $provider, ?OAuthConnection $connection): void
    {
        $definition = $this->registry->find($provider);
        if ($definition === null || $definition['multiple'] || !$this->tableAvailable()) {
            return;
        }

        $exists = OAuthConnection::query()
            ->where('provider', $provider)
            ->when($connection instanceof OAuthConnection, fn ($query) => $query->whereKeyNot($connection->getKey()))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'provider' => 'Only one ['.$provider.'] connection can be configured.',
            ]);
        }
    }

    private function assertManagedUpdateIsSafe(array $attributes): void
    {
        $allowed = [
            'enabled',
            'login_enabled',
            'registration_enabled',
            'linking_enabled',
            'sort_order',
        ];

        $blocked = array_diff(array_keys($attributes), $allowed);
        if ($blocked !== []) {
            throw ValidationException::withMessages([
                'connection_id' => 'Managed connection settings must be changed by the managing addon.',
            ]);
        }
    }

    /** @param array<string, mixed> $attributes */
    private function assertIdentityTrustBoundaryUnchanged(?OAuthConnection $connection, array $attributes): void
    {
        if (!$connection instanceof OAuthConnection || !$connection->identities()->exists()) {
            return;
        }

        if ($connection->provider !== $attributes['provider']) {
            throw ValidationException::withMessages([
                'provider' => 'The provider cannot be changed after identities have been linked.',
            ]);
        }

        $currentIssuer = $this->normalizeIssuer($connection->configuration['base_url'] ?? null);
        $updatedIssuer = $this->normalizeIssuer($attributes['configuration']['base_url'] ?? null);

        if ($currentIssuer !== $updatedIssuer) {
            throw ValidationException::withMessages([
                'base_url' => 'The issuer URL cannot be changed after identities have been linked.',
            ]);
        }
    }

    private function normalizeIssuer(mixed $issuer): ?string
    {
        return is_string($issuer) ? rtrim($issuer, '/') : null;
    }

    /**
     * @return Collection<int, OAuthConnection>
     */
    private function persistedConnections(): Collection
    {
        return OAuthConnection::query()
            ->orderBy('sort_order')
            ->orderBy('display_name')
            ->get();
    }

    /**
     * @return Collection<int, OAuthConnection>
     */
    private function legacyConnections(): Collection
    {
        return collect(['discord', 'vatsim', 'ivao'])
            ->map(function (string $provider): ?OAuthConnection {
                $configuration = (array) config('services.'.$provider, []);
                if (!(bool) ($configuration['enabled'] ?? false)) {
                    return null;
                }

                return new OAuthConnection([
                    'connection_id' => $provider,
                    'display_name'  => $this->registry->find($provider)['label'] ?? ucfirst($provider),
                    'provider'      => $provider,
                    'client_id'     => $configuration['client_id'] ?? null,
                    'client_secret' => $configuration['client_secret'] ?? null,
                    'scopes'        => $this->normalizeScopes($provider, $configuration['scopes'] ?? []),
                    'configuration' => Arr::except($configuration, [
                        'enabled',
                        'client_id',
                        'client_secret',
                        'scopes',
                        'redirect',
                    ]),
                    'enabled'              => true,
                    'login_enabled'        => true,
                    'registration_enabled' => false,
                    'linking_enabled'      => true,
                    'sort_order'           => 0,
                ]);
            })
            ->filter()
            ->values();
    }

    /**
     * @return list<string>
     */
    private function normalizeScopes(string $provider, mixed $scopes): array
    {
        if ($provider === 'vacentral') {
            return ['openid', 'profile', 'email'];
        }

        $configured = is_array($scopes) ? $scopes : [];

        if ($provider === 'openidconnect' && $configured === []) {
            return ['openid', 'email', 'profile'];
        }

        return collect([
            ...$this->registry->requiredScopes($provider),
            ...$configured,
        ])
            ->filter(static fn (mixed $scope): bool => is_string($scope) && $scope !== '')
            ->unique()
            ->values()
            ->all();
    }

    private function tableAvailable(): bool
    {
        try {
            return Schema::hasTable('oauth_connections');
        } catch (Throwable) {
            return false;
        }
    }
}

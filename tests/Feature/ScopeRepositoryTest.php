<?php

declare(strict_types=1);

use App\Auth\ScopeRepository;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\PermissionRegistry;
use Laravel\Passport\Bridge\Scope;
use Laravel\Passport\Passport;
use League\OAuth2\Server\Entities\ClientEntityInterface;

/*
 * App\Auth\ScopeRepository::finalizeScopes() — the generic permission-backed
 * OAuth scope gate (design.md §3). No plugin is installed in this checkout,
 * so a throwaway API scope is registered in-test instead of depending on
 * VMSAcars.
 */

beforeEach(function (): void {
    app(PermissionRegistry::class)->registerApiScope('x:test', 'API');

    // Passport's own finalizeScopes() filters by catalog membership first
    // (Passport::hasScope), so the throwaway scope plus the legacy scope used
    // below must be in the live catalog for the parent call to keep them.
    Passport::tokensCan(['x:test' => 'Test scope', 'user:read' => 'Read your profile']);

    // A client id with no matching row: Client::hasScope() defaults to true
    // when the `scopes` attribute is unset, but findActive() only reaches
    // that when a client is found — using an unknown id skips the client
    // filter in Passport's parent::finalizeScopes() entirely.
    $this->client = mock(ClientEntityInterface::class);
    $this->client->allows('getIdentifier')->andReturns('nonexistent-client');
});

function scopeRepo(): ScopeRepository
{
    return app(ScopeRepository::class);
}

it('keeps an API scope for a user who holds the matching permission', function (): void {
    Permission::firstOrCreate(['name' => 'x:test', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->givePermissionTo('x:test');

    $result = scopeRepo()->finalizeScopes([new Scope('x:test')], 'personal_access', $this->client, (string) $user->id);

    expect(collect($result)->map->getIdentifier()->all())->toContain('x:test');
});

it('strips an API scope from a user who does not hold the permission', function (): void {
    $user = User::factory()->create();

    $result = scopeRepo()->finalizeScopes([new Scope('x:test')], 'personal_access', $this->client, (string) $user->id);

    expect(collect($result)->map->getIdentifier()->all())->not->toContain('x:test');
});

it('drops API scopes when no user identifier is supplied', function (): void {
    $result = scopeRepo()->finalizeScopes([new Scope('x:test')], 'client_credentials', $this->client);

    expect(collect($result)->map->getIdentifier()->all())->not->toContain('x:test');
});

it('passes a legacy ApiScope value through untouched regardless of permissions', function (): void {
    $user = User::factory()->create();

    $result = scopeRepo()->finalizeScopes([new Scope('user:read')], 'personal_access', $this->client, (string) $user->id);

    expect(collect($result)->map->getIdentifier()->all())->toContain('user:read');
});

it('grants an API scope to a super-admin without an explicit permission grant', function (): void {
    $role = Role::create(['name' => Role::superAdminName(), 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole($role);

    $result = scopeRepo()->finalizeScopes([new Scope('x:test')], 'personal_access', $this->client, (string) $user->id);

    expect(collect($result)->map->getIdentifier()->all())->toContain('x:test');
});

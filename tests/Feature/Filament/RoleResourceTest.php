<?php

declare(strict_types=1);

use App\Filament\Resources\Roles\Pages\CreateRole;
use App\Filament\Resources\Roles\Pages\EditRole;
use App\Filament\Resources\Roles\RoleResource;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Policies\Filament\RolePolicy;
use App\Services\PermissionRegistry;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    Role::firstOrCreate(['name' => Role::superAdminName(), 'guard_name' => 'web']);
    $this->artisan('permission:sync');
});

it('creates a role with the selected matrix permissions', function (): void {
    $admin = createAdminUser();
    $this->actingAs($admin);

    Livewire::test(CreateRole::class)
        ->set('data.name', 'Dispatcher')
        ->set('data.permissions.resource_airline', ['view:airline', 'edit:airline'])
        ->call('create')
        ->assertHasNoFormErrors();

    $role = Role::where('name', 'Dispatcher')->first();

    expect($role)->not->toBeNull();
    expect($role->hasPermissionTo('view:airline'))->toBeTrue();
    expect($role->hasPermissionTo('edit:airline'))->toBeTrue();
    expect($role->hasPermissionTo('delete:airline'))->toBeFalse();
});

it('classifies a registered API scope into its own group, out of the scope tabs', function (): void {
    app(PermissionRegistry::class)->registerApiScope('x:test-scope', 'API', 'Test API Scope');

    $apiNames = collect(RoleResource::apiPermissionGroups())
        ->flatMap(static fn (array $group): array => array_column($group['permissions'], 'name'));
    $scopeNames = collect(RoleResource::scopePermissionGroups())
        ->flatMap(static fn (array $group): array => array_column($group['permissions'], 'name'));

    expect($apiNames)->toContain('x:test-scope')
        ->and($scopeNames)->not->toContain('x:test-scope');
});

it('renders the API Scopes tab and round-trips an API scope selection', function (): void {
    app(PermissionRegistry::class)->registerApiScope('x:test-scope', 'API', 'Test API Scope');

    $admin = createAdminUser();
    $this->actingAs($admin);

    Livewire::test(CreateRole::class)
        ->assertSee('API Scopes')
        ->assertSee('Test API Scope')
        ->set('data.name', 'Dispatcher')
        ->set('data.permissions.custom_API', ['x:test-scope'])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Role::where('name', 'Dispatcher')->first()?->hasPermissionTo('x:test-scope'))->toBeTrue();
});

it('shows an empty-state in the API Scopes tab when no API scopes are registered', function (): void {
    $admin = createAdminUser();
    $this->actingAs($admin);

    // Fresh registry (the singleton accumulates scopes across tests in-process);
    // core-only means no provider registered an API scope, so the tab takes its
    // empty-state branch (the hint itself renders on tab activation).
    app()->forgetInstance(PermissionRegistry::class);
    expect(RoleResource::apiPermissionGroups())->toBe([]);

    Livewire::test(CreateRole::class)->assertSee('API Scopes');
});

it('recreates a registry permission missing from the database when saving', function (): void {
    $admin = createAdminUser();
    $this->actingAs($admin);

    $role = Role::create(['name' => 'Dispatcher', 'guard_name' => 'web']);

    // Simulate the web matrix offering a permission the console sync never
    // persisted (e.g. a module access permission only visible in web context).
    Permission::where('name', 'edit:airline')->delete();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    Livewire::test(EditRole::class, ['record' => $role->id])
        ->set('data.permissions.resource_airline', ['view:airline', 'edit:airline'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Permission::where('name', 'edit:airline')->exists())->toBeTrue();
    expect($role->fresh()->hasPermissionTo('edit:airline'))->toBeTrue();
});

it('drops permission names that are not in the registry', function (): void {
    $admin = createAdminUser();
    $this->actingAs($admin);

    $role = Role::create(['name' => 'Dispatcher', 'guard_name' => 'web']);

    RoleResource::syncRolePermissions($role, ['view:airline', 'bogus:permission']);

    expect(Permission::where('name', 'bogus:permission')->exists())->toBeFalse();
    expect($role->fresh()->hasPermissionTo('view:airline'))->toBeTrue();
});

it('reflects the current grants in the matrix when editing', function (): void {
    $admin = createAdminUser();
    $this->actingAs($admin);

    $role = Role::create(['name' => 'Moderator', 'guard_name' => 'web']);
    $role->givePermissionTo('delete:user');

    Livewire::test(EditRole::class, ['record' => $role->id])
        ->assertSuccessful()
        ->assertSet('data.permissions.resource_user', ['delete:user'])
        ->assertSee('Airlines');
});

it('requires the view:role permission via the policy', function (): void {
    $policy = new RolePolicy();
    $user = User::factory()->create();

    expect($policy->viewAny($user))->toBeFalse();

    $user->givePermissionTo('view:role');

    expect($policy->viewAny($user->fresh()))->toBeTrue();
});

it('hides the permission matrix for the super admin role', function (): void {
    $admin = createAdminUser();
    $this->actingAs($admin);

    $superRole = Role::firstOrCreate([
        'name'       => Role::superAdminName(),
        'guard_name' => 'web',
    ]);

    Livewire::test(EditRole::class, ['record' => $superRole->id])
        ->assertSuccessful()
        ->assertDontSee('Airlines');
});

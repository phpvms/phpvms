<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;

it('lets a super admin pass every check', function (): void {
    $role = Role::create(['name' => Role::superAdminName(), 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole($role);

    // No permissions assigned, yet the super-admin bypasses everything.
    expect($user->can('view:user'))->toBeTrue();
    expect($user->can('delete:anything-at-all'))->toBeTrue();
    expect($user->can('a-permission-that-does-not-exist'))->toBeTrue();
});

it('makes a non super admin obey assigned permissions', function (): void {
    Permission::firstOrCreate(['name' => 'view:user', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'delete:user', 'guard_name' => 'web']);

    $user = User::factory()->create();
    $user->givePermissionTo('view:user');

    expect($user->can('view:user'))->toBeTrue();
    expect($user->can('delete:user'))->toBeFalse();
});

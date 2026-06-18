<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Filament\Facades\Filament;

it('grants admin access to a super admin', function (): void {
    $role = Role::create(['name' => Role::superAdminName(), 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole($role);

    expect($user->hasAdminAccess())->toBeTrue();
    expect($user->canAccessPanel(Filament::getPanel('admin')))->toBeTrue();
});

it('grants admin access via the view:dashboard permission', function (): void {
    Permission::firstOrCreate(['name' => 'view:dashboard', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->givePermissionTo('view:dashboard');

    expect($user->fresh()->hasAdminAccess())->toBeTrue();
});

it('denies admin access to a plain user', function (): void {
    $user = User::factory()->create();

    expect($user->hasAdminAccess())->toBeFalse();
    expect($user->canAccessPanel(Filament::getPanel('admin')))->toBeFalse();
});

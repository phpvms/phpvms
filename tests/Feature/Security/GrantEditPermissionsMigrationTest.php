<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;

function grantEditPermissionsMigration(): object
{
    return require base_path('database/migrations_data/2026_10_29_000000_grant_edit_on_maintenance_and_live_flights.php');
}

it('grants the new edit permissions to roles and users that could view the page', function (): void {
    $viewMaintenance = Permission::firstOrCreate(['name' => 'view:maintenance', 'guard_name' => 'web']);
    $viewLiveFlights = Permission::firstOrCreate(['name' => 'view:live-flights', 'guard_name' => 'web']);

    $role = Role::create(['name' => 'ops', 'guard_name' => 'web']);
    $role->givePermissionTo($viewMaintenance);

    $user = User::factory()->create();
    $user->givePermissionTo($viewLiveFlights);

    $bystander = Role::create(['name' => 'crew', 'guard_name' => 'web']);

    grantEditPermissionsMigration()->up();
    grantEditPermissionsMigration()->up();

    expect($role->fresh()->hasPermissionTo('edit:maintenance'))->toBeTrue()
        ->and($user->fresh()->hasPermissionTo('edit:live-flights'))->toBeTrue()
        ->and($bystander->fresh()->permissions)->toBeEmpty();
});

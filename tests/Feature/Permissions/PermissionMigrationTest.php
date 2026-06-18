<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

function seedV7ExportTables(): void
{
    Schema::create('v7_exported_roles', function (Blueprint $table): void {
        $table->increments('id');
        $table->string('name');
        $table->boolean('disable_activity_checks');
    });

    Schema::create('v7_exported_permission_role', function (Blueprint $table): void {
        $table->increments('id');
        $table->string('role_name');
        $table->string('permission_name');
    });

    Schema::create('v7_exported_role_user', function (Blueprint $table): void {
        $table->increments('id');
        $table->string('role_name');
        $table->integer('user_id');
    });
}

function runImportMigration(): void
{
    $migration = require base_path('database/migrations_data/2023_12_07_102243_import_roles_and_permissions.php');
    $migration->up();

    app(PermissionRegistrar::class)->forgetCachedPermissions();
}

it('collapses a v7 permission group to three abilities', function (): void {
    seedV7ExportTables();

    $user = User::factory()->create();

    DB::table('v7_exported_roles')->insert(['name' => 'Staff', 'disable_activity_checks' => true]);
    DB::table('v7_exported_role_user')->insert(['role_name' => 'Staff', 'user_id' => $user->id]);
    DB::table('v7_exported_permission_role')->insert(['role_name' => 'Staff', 'permission_name' => 'aircraft']);

    runImportMigration();

    $staff = Role::where('name', 'Staff')->first();

    expect($staff)->not->toBeNull();
    expect($staff->hasPermissionTo('view:aircraft'))->toBeTrue();
    expect($staff->hasPermissionTo('edit:aircraft'))->toBeTrue();
    expect($staff->hasPermissionTo('delete:aircraft'))->toBeTrue();

    // The old shield-style abilities never get created.
    expect(Permission::where('name', 'view-any:aircraft')->exists())->toBeFalse();
    expect(Permission::where('name', 'force-delete:aircraft')->exists())->toBeFalse();
});

it('renames the admin role and preserves assignments and the activity flag', function (): void {
    seedV7ExportTables();

    $user = User::factory()->create();

    DB::table('v7_exported_roles')->insert(['name' => 'Staff', 'disable_activity_checks' => true]);
    DB::table('v7_exported_role_user')->insert(['role_name' => 'admin', 'user_id' => $user->id]);
    DB::table('v7_exported_permission_role')->insert(['role_name' => 'admin', 'permission_name' => 'users']);

    runImportMigration();

    // admin -> super_admin
    expect(Role::where('name', Role::superAdminName())->exists())->toBeTrue();
    expect(Role::where('name', 'admin')->exists())->toBeFalse();

    // user assignment preserved (now under the super-admin name)
    expect($user->fresh()->hasRole(Role::superAdminName()))->toBeTrue();

    // disable_activity_checks carried over
    $staff = Role::where('name', 'Staff')->first();
    expect((bool) $staff->disable_activity_checks)->toBeTrue();
});

it('seeds the super-admin role and full permission catalog on a fresh install', function (): void {
    $this->seed(RolesPermissionsSeeder::class);

    expect(Role::where('name', Role::superAdminName())->exists())->toBeTrue();

    // No other role is created by default.
    expect(Role::where('name', '!=', Role::superAdminName())->exists())->toBeFalse();

    expect(Permission::where('name', 'view:user')->exists())->toBeTrue();
    expect(Permission::where('name', 'edit:airline')->exists())->toBeTrue();
    expect(Permission::where('name', 'view-logs')->exists())->toBeTrue();
    expect(Permission::where('name', 'create-backup')->exists())->toBeTrue();
});

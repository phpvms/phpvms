<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create the new super_admin role if it doesn't exist
        if (DB::table('roles')->where('name', config('filament-shield.super_admin.name'))->doesntExist()) {
            DB::table('roles')->insert([
                'id'                      => 1,
                'name'                    => config('filament-shield.super_admin.name'),
                'guard_name'              => 'web',
                'disable_activity_checks' => 1,
                'created_at'              => now(),
                'updated_at'              => now(),
            ]);
        }

        // First let's reimport the roles
        if (Schema::hasTable('v7_exported_roles')) {
            $exportedRoles = DB::table('v7_exported_roles')->get();
            foreach ($exportedRoles as $role) {
                DB::table('roles')->insert([
                    'name'                    => $role->name,
                    'guard_name'              => 'web',
                    'disable_activity_checks' => $role->disable_activity_checks,
                    'created_at'              => now(),
                    'updated_at'              => now(),
                ]);
            }
        }

        // Now let's reassign roles to the users
        if (Schema::hasTable('v7_exported_role_user')) {
            $exportedRoleUser = DB::table('v7_exported_role_user')->get();
            foreach ($exportedRoleUser as $roleUser) {
                if ($roleUser->role_name === 'admin') {
                    $roleUser->role_name = config('filament-shield.super_admin.name');
                }

                $roleId = DB::table('roles')->where('name', $roleUser->role_name)->value('id');

                DB::table('model_has_roles')->insert([
                    'role_id'    => $roleId,
                    'model_type' => 'App\Models\User',
                    'model_id'   => $roleUser->user_id,
                ]);
            }
        }

        // This is the permissionList between old ones (keys) and new ones (values)
        $permissions = [
            'admin-access' => [
                'view:dashboard',
                'view:news',
                'view:latest-pireps-chart',
            ],
            'addons' => [
                'view:modules',
            ],
            'aircraft' => [
                'view:aircraft',
                'view-any:aircraft',
                'create:aircraft',
                'update:aircraft',
                'restore:aircraft',
                'restore-any:aircraft',
                'replicate:aircraft',
                'reorder:aircraft',
                'delete:aircraft',
                'delete-any:aircraft',
                'force-delete:aircraft',
                'force-delete-any:aircraft',
            ],
            'airlines' => [
                'view:airline',
                'view-any:airline',
                'create:airline',
                'update:airline',
                'restore:airline',
                'restore-any:airline',
                'replicate:airline',
                'reorder:airline',
                'delete:airline',
                'delete-any:airline',
                'force-delete:airline',
                'force-delete-any:airline',
            ],
            'airports' => [
                'view:airport',
                'view-any:airport',
                'create:airport',
                'update:airport',
                'restore:airport',
                'restore-any:airport',
                'replicate:airport',
                'reorder:airport',
                'delete:airport',
                'delete-any:airport',
                'force-delete:airport',
                'force-delete-any:airport',
            ],
            'awards' => [
                'view:award',
                'view-any:award',
                'create:award',
                'update:award',
                'restore:award',
                'restore-any:award',
                'replicate:award',
                'reorder:award',
                'delete:award',
                'delete-any:award',
                'force-delete:award',
                'force-delete-any:award',
            ],
            'expenses' => [
                'view:expense',
                'view-any:expense',
                'create:expense',
                'update:expense',
                'restore:expense',
                'restore-any:expense',
                'replicate:expense',
                'reorder:expense',
                'delete:expense',
                'delete-any:expense',
                'force-delete:expense',
                'force-delete-any:expense',
            ],
            'fares' => [
                'view:fare',
                'view-any:fare',
                'create:fare',
                'update:fare',
                'restore:fare',
                'restore-any:fare',
                'replicate:fare',
                'reorder:fare',
                'delete:fare',
                'delete-any:fare',
                'force-delete:fare',
                'force-delete-any:fare',
            ],
            'finances' => [
                'view:finances',
                'view:airline-finance-chart',
                'view:airline-finance-table',
            ],
            'flights' => [
                'view:flight',
                'view-any:flight',
                'create:flight',
                'update:flight',
                'restore:flight',
                'restore-any:flight',
                'replicate:flight',
                'reorder:flight',
                'delete:flight',
                'delete-any:flight',
                'force-delete:flight',
                'force-delete-any:flight',
            ],
            'maintenance' => [
                'view:maintenance',
            ],
            'modules' => [
                'view:modules',
            ],
            'pages' => [
                'view:page',
                'view-any:page',
                'create:page',
                'update:page',
                'restore:page',
                'restore-any:page',
                'replicate:page',
                'reorder:page',
                'delete:page',
                'delete-any:page',
                'force-delete:page',
                'force-delete-any:page',
            ],
            'pireps' => [
                'view:pirep',
                'view-any:pirep',
                'create:pirep',
                'update:pirep',
                'restore:pirep',
                'restore-any:pirep',
                'replicate:pirep',
                'reorder:pirep',
                'delete:pirep',
                'delete-any:pirep',
                'force-delete:pirep',
                'force-delete-any:pirep',
                // Pirep fields
                'view:pirep-field',
                'view-any:pirep-field',
                'create:pirep-field',
                'update:pirep-field',
                'restore:pirep-field',
                'restore-any:pirep-field',
                'replicate:pirep-field',
                'reorder:pirep-field',
                'delete:pirep-field',
                'delete-any:pirep-field',
                'force-delete:pirep-field',
                'force-delete-any:pirep-field',
            ],
            'ranks' => [
                'view:rank',
                'view-any:rank',
                'create:rank',
                'update:rank',
                'restore:rank',
                'restore-any:rank',
                'replicate:rank',
                'reorder:rank',
                'delete:rank',
                'delete-any:rank',
                'force-delete:rank',
                'force-delete-any:rank',
            ],
            'settings' => [
                'view:settings',
            ],
            'subfleets' => [
                'view:subfleet',
                'view-any:subfleet',
                'create:subfleet',
                'update:subfleet',
                'restore:subfleet',
                'restore-any:subfleet',
                'replicate:subfleet',
                'reorder:subfleet',
                'delete:subfleet',
                'delete-any:subfleet',
                'force-delete:subfleet',
                'force-delete-any:subfleet',
            ],
            'typeratings' => [
                'view:typerating',
                'view-any:typerating',
                'create:typerating',
                'update:typerating',
                'restore:typerating',
                'restore-any:typerating',
                'replicate:typerating',
                'reorder:typerating',
                'delete:typerating',
                'delete-any:typerating',
                'force-delete:typerating',
                'force-delete-any:typerating',
            ],
            'users' => [
                'view:user',
                'view-any:user',
                'create:user',
                'update:user',
                'restore:user',
                'restore-any:user',
                'replicate:user',
                'reorder:user',
                'delete:user',
                'delete-any:user',
                'force-delete:user',
                'force-delete-any:user',
                // User fields
                'view:user-field',
                'view-any:user-field',
                'create:user-field',
                'update:user-field',
                'restore:user-field',
                'restore-any:user-field',
                'replicate:user-field',
                'reorder:user-field',
                'delete:user-field',
                'delete-any:user-field',
                'force-delete:user-field',
                'force-delete-any:user-field',
            ],
        ];

        // first let's create the permissions if they don't exist
        foreach ($permissions as $permissionList) {
            foreach ($permissionList as $permission) {
                if (DB::table('permissions')->where('name', $permission)->doesntExist()) {
                    DB::table('permissions')->insert([
                        'name'       => $permission,
                        'guard_name' => 'web',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        // now let's assign the permissions to the roles
        if (Schema::hasTable('v7_exported_permission_role')) {
            $exportedPermissionRole = DB::table('v7_exported_permission_role')->get();
            foreach ($exportedPermissionRole as $permission) {
                if ($permission->role_name === 'admin') {
                    $permission->role_name = config('filament-shield.super_admin.name');
                }

                $roleId = DB::table('roles')->where('name', $permission->role_name)->value('id');

                foreach ($permissions as $oldPermissionName => $newPermissionList) {
                    if (str_contains($permission->permission_name, $oldPermissionName)) {
                        foreach ($newPermissionList as $newPermission) {
                            $permissionId = DB::table('permissions')->where('name', $newPermission)->value('id');
                            if (DB::table('role_has_permissions')->where(['role_id' => $roleId, 'permission_id' => $permissionId])->exists()) {
                                continue;
                            }

                            DB::table('role_has_permissions')->insert([
                                'permission_id' => $permissionId,
                                'role_id'       => $roleId,
                            ]);
                        }
                    }
                }
            }
        }

        // Schema::dropIfExists('exported_roles');
        // Schema::dropIfExists('exported_role_user');
        // Schema::dropIfExists('exported_permission_role');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};

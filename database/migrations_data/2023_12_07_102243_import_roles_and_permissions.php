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
                'View:Dashboard',
                'View:News',
                'View:LatestPirepsChart',
            ],
            'addons' => [
                'View:Module',
                'ViewAny:Module',
                'Create:Module',
                'Update:Module',
                'Restore:Module',
                'RestoreAny:Module',
                'Replicate:Module',
                'Reorder:Module',
                'Delete:Module',
                'DeleteAny:Module',
                'ForceDelete:Module',
                'ForceDeleteAny:Module',
            ],
            'aircraft' => [
                'View:Aircraft',
                'ViewAny:Aircraft',
                'Create:Aircraft',
                'Update:Aircraft',
                'Restore:Aircraft',
                'RestoreAny:Aircraft',
                'Replicate:Aircraft',
                'Reorder:Aircraft',
                'Delete:Aircraft',
                'DeleteAny:Aircraft',
                'ForceDelete:Aircraft',
                'ForceDeleteAny:Aircraft',
            ],
            'airlines' => [
                'View:Airline',
                'ViewAny:Airline',
                'Create:Airline',
                'Update:Airline',
                'Restore:Airline',
                'RestoreAny:Airline',
                'Replicate:Airline',
                'Reorder:Airline',
                'Delete:Airline',
                'DeleteAny:Airline',
                'ForceDelete:Airline',
                'ForceDeleteAny:Airline',
            ],
            'airports' => [
                'View:Airport',
                'ViewAny:Airport',
                'Create:Airport',
                'Update:Airport',
                'Restore:Airport',
                'RestoreAny:Airport',
                'Replicate:Airport',
                'Reorder:Airport',
                'Delete:Airport',
                'DeleteAny:Airport',
                'ForceDelete:Airport',
                'ForceDeleteAny:Airport',
            ],
            'awards' => [
                'View:Award',
                'ViewAny:Award',
                'Create:Award',
                'Update:Award',
                'Restore:Award',
                'RestoreAny:Award',
                'Replicate:Award',
                'Reorder:Award',
                'Delete:Award',
                'DeleteAny:Award',
                'ForceDelete:Award',
                'ForceDeleteAny:Award',
            ],
            'expenses' => [
                'View:Expense',
                'ViewAny:Expense',
                'Create:Expense',
                'Update:Expense',
                'Restore:Expense',
                'RestoreAny:Expense',
                'Replicate:Expense',
                'Reorder:Expense',
                'Delete:Expense',
                'DeleteAny:Expense',
                'ForceDelete:Expense',
                'ForceDeleteAny:Expense',
            ],
            'fares' => [
                'View:Fare',
                'ViewAny:Fare',
                'Create:Fare',
                'Update:Fare',
                'Restore:Fare',
                'RestoreAny:Fare',
                'Replicate:Fare',
                'Reorder:Fare',
                'Delete:Fare',
                'DeleteAny:Fare',
                'ForceDelete:Fare',
                'ForceDeleteAny:Fare',
            ],
            'finances' => [
                'View:Finances',
                'View:AirlineFinanceChart',
                'View:AirlineFinanceTable',
            ],
            'flights' => [
                'View:Flight',
                'ViewAny:Flight',
                'Create:Flight',
                'Update:Flight',
                'Restore:Flight',
                'RestoreAny:Flight',
                'Replicate:Flight',
                'Reorder:Flight',
                'Delete:Flight',
                'DeleteAny:Flight',
                'ForceDelete:Flight',
                'ForceDeleteAny:Flight',
            ],
            'maintenance' => [
                'View:Maintenance',
            ],
            'modules' => [
                'View:Module',
                'ViewAny:Module',
                'Create:Module',
                'Update:Module',
                'Restore:Module',
                'RestoreAny:Module',
                'Replicate:Module',
                'Reorder:Module',
                'Delete:Module',
                'DeleteAny:Module',
                'ForceDelete:Module',
                'ForceDeleteAny:Module',
            ],
            'pages' => [
                'View:Page',
                'ViewAny:Page',
                'Create:Page',
                'Update:Page',
                'Restore:Page',
                'RestoreAny:Page',
                'Replicate:Page',
                'Reorder:Page',
                'Delete:Page',
                'DeleteAny:Page',
                'ForceDelete:Page',
                'ForceDeleteAny:Page',
            ],
            'pireps' => [
                'View:Pirep',
                'ViewAny:Pirep',
                'Create:Pirep',
                'Update:Pirep',
                'Restore:Pirep',
                'RestoreAny:Pirep',
                'Replicate:Pirep',
                'Reorder:Pirep',
                'Delete:Pirep',
                'DeleteAny:Pirep',
                'ForceDelete:Pirep',
                'ForceDeleteAny:Pirep',
                // Pirep fields
                'View:Pirepfield',
                'ViewAny:Pirepfield',
                'Create:Pirepfield',
                'Update:Pirepfield',
                'Restore:Pirepfield',
                'RestoreAny:Pirepfield',
                'Replicate:Pirepfield',
                'Reorder:Pirepfield',
                'Delete:Pirepfield',
                'DeleteAny:Pirepfield',
                'ForceDelete:Pirepfield',
                'ForceDeleteAny:Pirepfield',
            ],
            'ranks' => [
                'View:Rank',
                'ViewAny:Rank',
                'Create:Rank',
                'Update:Rank',
                'Restore:Rank',
                'RestoreAny:Rank',
                'Replicate:Rank',
                'Reorder:Rank',
                'Delete:Rank',
                'DeleteAny:Rank',
                'ForceDelete:Rank',
                'ForceDeleteAny:Rank',
            ],
            'settings' => [
                'View:Settings',
            ],
            'subfleets' => [
                'View:Subfleet',
                'ViewAny:Subfleet',
                'Create:Subfleet',
                'Update:Subfleet',
                'Restore:Subfleet',
                'RestoreAny:Subfleet',
                'Replicate:Subfleet',
                'Reorder:Subfleet',
                'Delete:Subfleet',
                'DeleteAny:Subfleet',
                'ForceDelete:Subfleet',
                'ForceDeleteAny:Subfleet',
            ],
            'typeratings' => [
                'View:Typerating',
                'ViewAny:Typerating',
                'Create:Typerating',
                'Update:Typerating',
                'Restore:Typerating',
                'RestoreAny:Typerating',
                'Replicate:Typerating',
                'Reorder:Typerating',
                'Delete:Typerating',
                'DeleteAny:Typerating',
                'ForceDelete:Typerating',
                'ForceDeleteAny:Typerating',
            ],
            'users' => [
                'View:User',
                'ViewAny:User',
                'Create:User',
                'Update:User',
                'Restore:User',
                'RestoreAny:User',
                'Replicate:User',
                'Reorder:User',
                'Delete:User',
                'DeleteAny:User',
                'ForceDelete:User',
                'ForceDeleteAny:User',
                // User fields
                'View:Userfield',
                'ViewAny:Userfield',
                'Create:Userfield',
                'Update:Userfield',
                'Restore:Userfield',
                'RestoreAny:Userfield',
                'Replicate:Userfield',
                'Reorder:Userfield',
                'Delete:Userfield',
                'DeleteAny:Userfield',
                'ForceDelete:Userfield',
                'ForceDeleteAny:Userfield',
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

<?php

use App\Models\User;
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
        $superAdmin = config('roles.super_admin', 'super_admin');

        // Create the new super_admin role if it doesn't exist
        if (DB::table('roles')->where('name', $superAdmin)->doesntExist()) {
            DB::table('roles')->insert([
                'id'                      => 1,
                'name'                    => $superAdmin,
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
                    $roleUser->role_name = $superAdmin;
                }

                $roleId = DB::table('roles')->where('name', $roleUser->role_name)->value('id');

                DB::table('model_has_roles')->insert([
                    'role_id'    => $roleId,
                    'model_type' => User::class,
                    'model_id'   => $roleUser->user_id,
                ]);
            }
        }

        // Mapping between old v7 permission groups (keys) and the new three-ability
        // permission names (values). v8 collapses the old per-action matrix to
        // view/edit/delete per subject: create/update/duplicate => edit,
        // delete/force-delete/restore => delete, list/view/reorder => view.
        $permissions = [
            'admin-access' => [
                'view:dashboard',
                'view:news',
            ],
            'addons' => [
                'view:modules',
            ],
            'aircraft' => [
                'view:aircraft',
                'edit:aircraft',
                'delete:aircraft',
            ],
            'airlines' => [
                'view:airline',
                'edit:airline',
                'delete:airline',
            ],
            'airports' => [
                'view:airport',
                'edit:airport',
                'delete:airport',
            ],
            'awards' => [
                'view:award',
                'edit:award',
                'delete:award',
            ],
            'expenses' => [
                'view:expense',
                'edit:expense',
                'delete:expense',
            ],
            'fares' => [
                'view:fare',
                'edit:fare',
                'delete:fare',
            ],
            'finances' => [
                'view:finances',
            ],
            'flights' => [
                'view:flight',
                'edit:flight',
                'delete:flight',
            ],
            'maintenance' => [
                'view:maintenance',
            ],
            'modules' => [
                'view:modules',
            ],
            'pages' => [
                'view:page',
                'edit:page',
                'delete:page',
            ],
            'pireps' => [
                'view:pirep',
                'edit:pirep',
                'delete:pirep',
                // Pirep fields
                'view:pirep-field',
                'edit:pirep-field',
                'delete:pirep-field',
            ],
            'ranks' => [
                'view:rank',
                'edit:rank',
                'delete:rank',
            ],
            'settings' => [
                'view:settings',
                'edit:settings',
            ],
            'subfleets' => [
                'view:subfleet',
                'edit:subfleet',
                'delete:subfleet',
            ],
            'typeratings' => [
                'view:typerating',
                'edit:typerating',
                'delete:typerating',
            ],
            'users' => [
                'view:user',
                'edit:user',
                'delete:user',
                // User fields
                'view:user-field',
                'edit:user-field',
                'delete:user-field',
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
                    $permission->role_name = $superAdmin;
                }

                $roleId = DB::table('roles')->where('name', $permission->role_name)->value('id');

                foreach ($permissions as $oldPermissionName => $newPermissionList) {
                    if (str_contains((string) $permission->permission_name, $oldPermissionName)) {
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

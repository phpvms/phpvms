<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
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
                'disable_activity_checks' => 0,
                'created_at'              => now(),
                'updated_at'              => now(),
            ]);
        }

        // First let's reimport the roles
        $exportedRoles = DB::table('exported_roles')->get();
        foreach ($exportedRoles as $role) {
            if ($role->name === 'admin') {
                continue;
            }

            DB::table('roles')->insert([
                'name'                    => $role->name,
                'guard_name'              => 'web',
                'disable_activity_checks' => $role->disable_activity_checks,
                'created_at'              => now(),
                'updated_at'              => now(),
            ]);
        }

        //Schema::dropIfExists('exported_roles');

        // Now let's reassign roles to the users
        $exportedRoleUser = DB::table('exported_role_user')->get();
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

        //Schema::dropIfExists('exported_role_user');

        // This is the permissionList between old ones (keys) and new ones (values)
        $permissions = [
            'admin-access' => [
                'page_Dashboard',
                'widget_AccountWidget',
                'widget_FilamentInfoWidget',
                'widget_News',
                'widget_LatestPirepsChart',
            ],
            'addons' => [
                'view_module',
                'view_any_module',
                'create_module',
                'update_module',
                'restore_module',
                'restore_any_module',
                'replicate_module',
                'reorder_module',
                'delete_module',
                'delete_any_module',
                'force_delete_module',
                'force_delete_any_module',
            ],
            'aircraft' => [
                'view_aircraft',
                'view_any_aircraft',
                'create_aircraft',
                'update_aircraft',
                'restore_aircraft',
                'restore_any_aircraft',
                'replicate_aircraft',
                'reorder_aircraft',
                'delete_aircraft',
                'delete_any_aircraft',
                'force_delete_aircraft',
                'force_delete_any_aircraft',
            ],
            'airlines' => [
                'view_airline',
                'view_any_airline',
                'create_airline',
                'update_airline',
                'restore_airline',
                'restore_any_airline',
                'replicate_airline',
                'reorder_airline',
                'delete_airline',
                'delete_any_airline',
                'force_delete_airline',
                'force_delete_any_airline',
            ],
            'airports' => [
                'view_airport',
                'view_any_airport',
                'create_airport',
                'update_airport',
                'restore_airport',
                'restore_any_airport',
                'replicate_airport',
                'reorder_airport',
                'delete_airport',
                'delete_any_airport',
                'force_delete_airport',
                'force_delete_any_airport',
            ],
            'awards' => [
                'view_award',
                'view_any_award',
                'create_award',
                'update_award',
                'restore_award',
                'restore_any_award',
                'replicate_award',
                'reorder_award',
                'delete_award',
                'delete_any_award',
                'force_delete_award',
                'force_delete_any_award',
            ],
            'expenses' => [
                'view_expense',
                'view_any_expense',
                'create_expense',
                'update_expense',
                'restore_expense',
                'restore_any_expense',
                'replicate_expense',
                'reorder_expense',
                'delete_expense',
                'delete_any_expense',
                'force_delete_expense',
                'force_delete_any_expense',
            ],
            'fares' => [
                'view_fare',
                'view_any_fare',
                'create_fare',
                'update_fare',
                'restore_fare',
                'restore_any_fare',
                'replicate_fare',
                'reorder_fare',
                'delete_fare',
                'delete_any_fare',
                'force_delete_fare',
                'force_delete_any_fare',
            ],
            'finances' => [
                'page_Finances',
                'widget_AirlineFinanceChart',
                'widget_AirlineFinanceTable',
            ],
            'flights' => [
                'view_flight',
                'view_any_flight',
                'create_flight',
                'update_flight',
                'restore_flight',
                'restore_any_flight',
                'replicate_flight',
                'reorder_flight',
                'delete_flight',
                'delete_any_flight',
                'force_delete_flight',
                'force_delete_any_flight',
            ],
            'maintenance' => [
                'page_Maintenance',
            ],
            'modules' => [
                'view_module',
                'view_any_module',
                'create_module',
                'update_module',
                'restore_module',
                'restore_any_module',
                'replicate_module',
                'reorder_module',
                'delete_module',
                'delete_any_module',
                'force_delete_module',
                'force_delete_any_module',
            ],
            'pages' => [
                'view_page',
                'view_any_page',
                'create_page',
                'update_page',
                'restore_page',
                'restore_any_page',
                'replicate_page',
                'reorder_page',
                'delete_page',
                'delete_any_page',
                'force_delete_page',
                'force_delete_any_page',
            ],
            'pireps' => [
                'view_pirep',
                'view_any_pirep',
                'create_pirep',
                'update_pirep',
                'restore_pirep',
                'restore_any_pirep',
                'replicate_pirep',
                'reorder_pirep',
                'delete_pirep',
                'delete_any_pirep',
                'force_delete_pirep',
                'force_delete_any_pirep',
                'view_pirep::field',
                'view_any_pirep::field',
                'create_pirep::field',
                'update_pirep::field',
                'restore_pirep::field',
                'restore_any_pirep::field',
                'replicate_pirep::field',
                'reorder_pirep::field',
                'delete_pirep::field',
                'delete_any_pirep::field',
                'force_delete_pirep::field',
                'force_delete_any_pirep::field',
            ],
            'ranks' => [
                'view_rank',
                'view_any_rank',
                'create_rank',
                'update_rank',
                'restore_rank',
                'restore_any_rank',
                'replicate_rank',
                'reorder_rank',
                'delete_rank',
                'delete_any_rank',
                'force_delete_rank',
                'force_delete_any_rank',
            ],
            'settings' => [
                'page_Settings',
            ],
            'subfleets' => [
                'view_subfleet',
                'view_any_subfleet',
                'create_subfleet',
                'update_subfleet',
                'restore_subfleet',
                'restore_any_subfleet',
                'replicate_subfleet',
                'reorder_subfleet',
                'delete_subfleet',
                'delete_any_subfleet',
                'force_delete_subfleet',
                'force_delete_any_subfleet',
            ],
            'typeratings' => [
                'view_typerating',
                'view_any_typerating',
                'create_typerating',
                'update_typerating',
                'restore_typerating',
                'restore_any_typerating',
                'replicate_typerating',
                'reorder_typerating',
                'delete_typerating',
                'delete_any_typerating',
                'force_delete_typerating',
                'force_delete_any_typerating',
            ],
            'users' => [
                'view_user',
                'view_any_user',
                'create_user',
                'update_user',
                'restore_user',
                'restore_any_user',
                'replicate_user',
                'reorder_user',
                'delete_user',
                'delete_any_user',
                'force_delete_user',
                'force_delete_any_user',
                'view_user::field',
                'view_any_user::field',
                'create_user::field',
                'update_user::field',
                'restore_user::field',
                'restore_any_user::field',
                'replicate_user::field',
                'reorder_user::field',
                'delete_user::field',
                'delete_any_user::field',
                'force_delete_user::field',
                'force_delete_any_user::field',
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
        $exportedPermissionRole = DB::table('exported_permission_role')->get();
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

        //Schema::dropIfExists('exported_permission_role');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};

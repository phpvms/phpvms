<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Exported roles
        if (Schema::hasTable('roles') && DB::table('roles')->where('name', '!=', 'admin')->count() > 0) {
            Schema::create('v7_exported_roles', function (Blueprint $table) {
                $table->increments('id');
                $table->string('name');
                $table->boolean('disable_activity_checks');
            });

            $exportedRoles = DB::table('roles')->where('name', '!=', 'admin')->select(['name', 'disable_activity_checks'])->get();
            foreach ($exportedRoles as $role) {
                DB::table('v7_exported_roles')->insert([
                    'name'                    => $role->name,
                    'disable_activity_checks' => $role->disable_activity_checks,
                ]);
            }
        }

        // Exported permission_role
        if (Schema::hasTable('permission_role') && DB::table('permission_role')->count() > 0) {
            Schema::create('v7_exported_permission_role', function (Blueprint $table) {
                $table->increments('id');
                $table->string('role_name');
                $table->string('permission_name');
            });

            $permissionRoles = DB::table('permission_role')
                ->join('permissions', 'permission_role.permission_id', '=', 'permissions.id')
                ->join('roles', 'permission_role.role_id', '=', 'roles.id')
                ->select('roles.name as role_name', 'permissions.name as permission_name')
                ->get();

            foreach ($permissionRoles as $permissionRole) {
                DB::table('v7_exported_permission_role')->insert([
                    'role_name'       => $permissionRole->role_name,
                    'permission_name' => $permissionRole->permission_name,
                ]);
            }
        }

        // Exported role_user
        if (Schema::hasTable('role_user') && DB::table('role_user')->count() > 0) {
            Schema::create('v7_exported_role_user', function (Blueprint $table) {
                $table->increments('id');
                $table->string('role_name');
                $table->integer('user_id');
            });

            $roleUsers = DB::table('role_user')
                ->join('roles', 'role_user.role_id', '=', 'roles.id')
                ->select('roles.name as role_name', 'role_user.user_id')
                ->get();

            foreach ($roleUsers as $roleUser) {
                DB::table('v7_exported_role_user')->insert([
                    'role_name' => $roleUser->role_name,
                    'user_id'   => $roleUser->user_id,
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('v7_exported_roles');
        Schema::dropIfExists('v7_exported_permission_role');
        Schema::dropIfExists('v7_exported_role_user');
    }
};

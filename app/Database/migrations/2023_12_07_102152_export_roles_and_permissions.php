<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Exported roles
        Schema::create('exported_roles', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->boolean('disable_activity_checks');
        });

        $exportedRoles = DB::table('roles')->select(['name', 'disable_activity_checks'])->get();
        foreach ($exportedRoles as $role) {
            DB::table('exported_roles')->insert([
                'name' => $role->name,
                'disable_activity_checks' => $role->disable_activity_checks
                ]);
        }

        // Exported permission_role
        Schema::create('exported_permission_role', function (Blueprint $table) {
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
            DB::table('exported_permission_role')->insert([
                'role_name' => $permissionRole->role_name,
                'permission_name' => $permissionRole->permission_name
                ]);
        }

        // Exported role_user
        Schema::create('exported_role_user', function (Blueprint $table) {
            $table->increments('id');
            $table->string('role_name');
            $table->integer('user_id');
        });

        $roleUsers = DB::table('role_user')
                ->join('roles', 'role_user.role_id', '=', 'roles.id')
                ->select('roles.name as role_name', 'role_user.user_id')
                ->get();

        foreach ($roleUsers as $roleUser) {
            DB::table('exported_role_user')->insert([
                'role_name' => $roleUser->role_name,
                'user_id' => $roleUser->user_id
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exported_roles');
        Schema::dropIfExists('exported_permission_role');
        Schema::dropIfExists('exported_role_user');
    }
};

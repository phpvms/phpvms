<?php

namespace App\Database\seeds;

use BezhanSalleh\FilamentShield\Support\Utils;
use Illuminate\Database\Seeder;

class ShieldSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $rolesWithPermissions = '[{"name":"super_admin","guard_name":"web","permissions":[]},{"name":"admin","guard_name":"web","permissions":["view_aircraft","view_any_aircraft","create_aircraft","update_aircraft","restore_aircraft","restore_any_aircraft","replicate_aircraft","reorder_aircraft","delete_aircraft","delete_any_aircraft","force_delete_aircraft","force_delete_any_aircraft","view_airline","view_any_airline","create_airline","update_airline","restore_airline","restore_any_airline","replicate_airline","reorder_airline","delete_airline","delete_any_airline","force_delete_airline","force_delete_any_airline","view_airport","view_any_airport","create_airport","update_airport","restore_airport","restore_any_airport","replicate_airport","reorder_airport","delete_airport","delete_any_airport","force_delete_airport","force_delete_any_airport","view_award","view_any_award","create_award","update_award","restore_award","restore_any_award","replicate_award","reorder_award","delete_award","delete_any_award","force_delete_award","force_delete_any_award","view_expense","view_any_expense","create_expense","update_expense","restore_expense","restore_any_expense","replicate_expense","reorder_expense","delete_expense","delete_any_expense","force_delete_expense","force_delete_any_expense","view_fare","view_any_fare","create_fare","update_fare","restore_fare","restore_any_fare","replicate_fare","reorder_fare","delete_fare","delete_any_fare","force_delete_fare","force_delete_any_fare","view_flight","view_any_flight","create_flight","update_flight","restore_flight","restore_any_flight","replicate_flight","reorder_flight","delete_flight","delete_any_flight","force_delete_flight","force_delete_any_flight","view_module","view_any_module","create_module","update_module","restore_module","restore_any_module","replicate_module","reorder_module","delete_module","delete_any_module","force_delete_module","force_delete_any_module","view_page","view_any_page","create_page","update_page","restore_page","restore_any_page","replicate_page","reorder_page","delete_page","delete_any_page","force_delete_page","force_delete_any_page","view_pirep","view_any_pirep","create_pirep","update_pirep","restore_pirep","restore_any_pirep","replicate_pirep","reorder_pirep","delete_pirep","delete_any_pirep","force_delete_pirep","force_delete_any_pirep","view_pirep::field","view_any_pirep::field","create_pirep::field","update_pirep::field","restore_pirep::field","restore_any_pirep::field","replicate_pirep::field","reorder_pirep::field","delete_pirep::field","delete_any_pirep::field","force_delete_pirep::field","force_delete_any_pirep::field","view_rank","view_any_rank","create_rank","update_rank","restore_rank","restore_any_rank","replicate_rank","reorder_rank","delete_rank","delete_any_rank","force_delete_rank","force_delete_any_rank","view_subfleet","view_any_subfleet","create_subfleet","update_subfleet","restore_subfleet","restore_any_subfleet","replicate_subfleet","reorder_subfleet","delete_subfleet","delete_any_subfleet","force_delete_subfleet","force_delete_any_subfleet","view_typerating","view_any_typerating","create_typerating","update_typerating","restore_typerating","restore_any_typerating","replicate_typerating","reorder_typerating","delete_typerating","delete_any_typerating","force_delete_typerating","force_delete_any_typerating","view_user","view_any_user","create_user","update_user","restore_user","restore_any_user","replicate_user","reorder_user","delete_user","delete_any_user","force_delete_user","force_delete_any_user","view_user::field","view_any_user::field","create_user::field","update_user::field","restore_user::field","restore_any_user::field","replicate_user::field","reorder_user::field","delete_user::field","delete_any_user::field","force_delete_user::field","force_delete_any_user::field","page_Finances","page_Maintenance","page_Settings","page_Dashboard","widget_AccountWidget","widget_FilamentInfoWidget","widget_AirlineFinanceChart","widget_AirlineFinanceTable","widget_News","widget_LatestPirepsChart"]}]';
        $directPermissions = '[]';

        static::makeRolesWithPermissions($rolesWithPermissions);
        static::makeDirectPermissions($directPermissions);

        $this->command?->info('Shield Seeding Completed.');
    }

    protected static function makeRolesWithPermissions(string $rolesWithPermissions): void
    {
        if (!blank($rolePlusPermissions = json_decode($rolesWithPermissions, true))) {
            foreach ($rolePlusPermissions as $rolePlusPermission) {
                $role = Utils::getRoleModel()::firstOrCreate([
                    'name'       => $rolePlusPermission['name'],
                    'guard_name' => $rolePlusPermission['guard_name'],
                ]);

                if (!blank($rolePlusPermission['permissions'])) {
                    $permissionModels = collect();

                    collect($rolePlusPermission['permissions'])
                        ->each(function ($permission) use ($permissionModels) {
                            $permissionModels->push(Utils::getPermissionModel()::firstOrCreate([
                                'name'       => $permission,
                                'guard_name' => 'web',
                            ]));
                        });
                    $role->syncPermissions($permissionModels);
                }
            }
        }
    }

    public static function makeDirectPermissions(string $directPermissions): void
    {
        if (!blank($permissions = json_decode($directPermissions, true))) {
            foreach ($permissions as $permission) {
                if (Utils::getPermissionModel()::whereName($permission)->doesntExist()) {
                    Utils::getPermissionModel()::create([
                        'name'       => $permission['name'],
                        'guard_name' => $permission['guard_name'],
                    ]);
                }
            }
        }
    }
}

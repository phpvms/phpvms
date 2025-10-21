<?php

namespace Database\Seeders;

use BezhanSalleh\FilamentShield\Support\Utils;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class ShieldSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $rolesWithPermissions = '[{"name":"super_admin","guard_name":"web","permissions":["ViewAny:Role","View:Role","Create:Role","Update:Role","Delete:Role","Restore:Role","ForceDelete:Role","ForceDeleteAny:Role","RestoreAny:Role","Replicate:Role","Reorder:Role","ViewAny:Activity","View:Activity","Create:Activity","Update:Activity","Delete:Activity","Restore:Activity","ForceDelete:Activity","ForceDeleteAny:Activity","RestoreAny:Activity","Replicate:Activity","Reorder:Activity","ViewAny:Airline","View:Airline","Create:Airline","Update:Airline","Delete:Airline","Restore:Airline","ForceDelete:Airline","ForceDeleteAny:Airline","RestoreAny:Airline","Replicate:Airline","Reorder:Airline","ViewAny:Airport","View:Airport","Create:Airport","Update:Airport","Delete:Airport","Restore:Airport","ForceDelete:Airport","ForceDeleteAny:Airport","RestoreAny:Airport","Replicate:Airport","Reorder:Airport","ViewAny:Award","View:Award","Create:Award","Update:Award","Delete:Award","Restore:Award","ForceDelete:Award","ForceDeleteAny:Award","RestoreAny:Award","Replicate:Award","Reorder:Award","ViewAny:Expense","View:Expense","Create:Expense","Update:Expense","Delete:Expense","Restore:Expense","ForceDelete:Expense","ForceDeleteAny:Expense","RestoreAny:Expense","Replicate:Expense","Reorder:Expense","ViewAny:Fare","View:Fare","Create:Fare","Update:Fare","Delete:Fare","Restore:Fare","ForceDelete:Fare","ForceDeleteAny:Fare","RestoreAny:Fare","Replicate:Fare","Reorder:Fare","ViewAny:Flight","View:Flight","Create:Flight","Update:Flight","Delete:Flight","Restore:Flight","ForceDelete:Flight","ForceDeleteAny:Flight","RestoreAny:Flight","Replicate:Flight","Reorder:Flight","ViewAny:Invite","View:Invite","Create:Invite","Update:Invite","Delete:Invite","Restore:Invite","ForceDelete:Invite","ForceDeleteAny:Invite","RestoreAny:Invite","Replicate:Invite","Reorder:Invite","ViewAny:Module","View:Module","Create:Module","Update:Module","Delete:Module","Restore:Module","ForceDelete:Module","ForceDeleteAny:Module","RestoreAny:Module","Replicate:Module","Reorder:Module","ViewAny:Page","View:Page","Create:Page","Update:Page","Delete:Page","Restore:Page","ForceDelete:Page","ForceDeleteAny:Page","RestoreAny:Page","Replicate:Page","Reorder:Page","ViewAny:PirepField","View:PirepField","Create:PirepField","Update:PirepField","Delete:PirepField","Restore:PirepField","ForceDelete:PirepField","ForceDeleteAny:PirepField","RestoreAny:PirepField","Replicate:PirepField","Reorder:PirepField","ViewAny:Pirep","View:Pirep","Create:Pirep","Update:Pirep","Delete:Pirep","Restore:Pirep","ForceDelete:Pirep","ForceDeleteAny:Pirep","RestoreAny:Pirep","Replicate:Pirep","Reorder:Pirep","ViewAny:Rank","View:Rank","Create:Rank","Update:Rank","Delete:Rank","Restore:Rank","ForceDelete:Rank","ForceDeleteAny:Rank","RestoreAny:Rank","Replicate:Rank","Reorder:Rank","ViewAny:SimBriefAirframe","View:SimBriefAirframe","Create:SimBriefAirframe","Update:SimBriefAirframe","Delete:SimBriefAirframe","Restore:SimBriefAirframe","ForceDelete:SimBriefAirframe","ForceDeleteAny:SimBriefAirframe","RestoreAny:SimBriefAirframe","Replicate:SimBriefAirframe","Reorder:SimBriefAirframe","ViewAny:Aircraft","View:Aircraft","Create:Aircraft","Update:Aircraft","Delete:Aircraft","Restore:Aircraft","ForceDelete:Aircraft","ForceDeleteAny:Aircraft","RestoreAny:Aircraft","Replicate:Aircraft","Reorder:Aircraft","ViewAny:Subfleet","View:Subfleet","Create:Subfleet","Update:Subfleet","Delete:Subfleet","Restore:Subfleet","ForceDelete:Subfleet","ForceDeleteAny:Subfleet","RestoreAny:Subfleet","Replicate:Subfleet","Reorder:Subfleet","ViewAny:Typerating","View:Typerating","Create:Typerating","Update:Typerating","Delete:Typerating","Restore:Typerating","ForceDelete:Typerating","ForceDeleteAny:Typerating","RestoreAny:Typerating","Replicate:Typerating","Reorder:Typerating","ViewAny:UserField","View:UserField","Create:UserField","Update:UserField","Delete:UserField","Restore:UserField","ForceDelete:UserField","ForceDeleteAny:UserField","RestoreAny:UserField","Replicate:UserField","Reorder:UserField","ViewAny:User","View:User","Create:User","Update:User","Delete:User","Restore:User","ForceDelete:User","ForceDeleteAny:User","RestoreAny:User","Replicate:User","Reorder:User","View:Backups","View:Dashboard","View:Finances","View:Maintenance","View:Settings","View:AirlineFinanceChart","View:AirlineFinanceTable","View:VersionWidget","View:News","View:LatestPirepsChart"]}]';
        $directPermissions = '[]';

        static::makeRolesWithPermissions($rolesWithPermissions);
        static::makeDirectPermissions($directPermissions);

        $this->command->info('Shield Seeding Completed.');
    }

    protected static function makeRolesWithPermissions(string $rolesWithPermissions): void
    {
        if (!blank($rolePlusPermissions = json_decode($rolesWithPermissions, true))) {
            /** @var Model $roleModel */
            $roleModel = Utils::getRoleModel();
            /** @var Model $permissionModel */
            $permissionModel = Utils::getPermissionModel();

            foreach ($rolePlusPermissions as $rolePlusPermission) {
                $role = $roleModel::firstOrCreate([
                    'name'       => $rolePlusPermission['name'],
                    'guard_name' => $rolePlusPermission['guard_name'],
                ]);

                if (!blank($rolePlusPermission['permissions'])) {
                    $permissionModels = collect($rolePlusPermission['permissions'])
                        ->map(fn ($permission) => $permissionModel::firstOrCreate([
                            'name'       => $permission,
                            'guard_name' => $rolePlusPermission['guard_name'],
                        ]))
                        ->all();

                    $role->syncPermissions($permissionModels);
                }
            }
        }
    }

    public static function makeDirectPermissions(string $directPermissions): void
    {
        if (!blank($permissions = json_decode($directPermissions, true))) {
            /** @var Model $permissionModel */
            $permissionModel = Utils::getPermissionModel();

            foreach ($permissions as $permission) {
                if ($permissionModel::whereName($permission)->doesntExist()) {
                    $permissionModel::create([
                        'name'       => $permission['name'],
                        'guard_name' => $permission['guard_name'],
                    ]);
                }
            }
        }
    }
}

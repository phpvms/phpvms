<?php

namespace Database\Seeders;

use BezhanSalleh\FilamentShield\Support\Utils;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class ShieldSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $rolesWithPermissions = [
            [
                'name'        => 'super_admin',
                'guard_name'  => 'web',
                'permissions' => [
                    'view:modules',
                    'view-logs',
                    'download-backup',
                    'delete-backup',
                    'create-backup',
                ],
            ],
            [
                'name'        => 'Pilot',
                'guard_name'  => 'web',
                'permissions' => [
                    'view-logs',
                    'download-backup',
                    'delete-backup',
                    'create-backup',
                    'view:modules',
                ],
            ],
        ];

        static::makeRolesWithPermissions($rolesWithPermissions);
        static::makeDirectPermissions([]);

        $this->command->info('Shield Seeding Completed.');
    }

    protected static function makeRolesWithPermissions(array $rolesWithPermissions): void
    {
        if (blank($rolesWithPermissions)) {
            return;
        }

        $rolePlusPermissions = $rolesWithPermissions;

        /** @var Model $roleModel */
        $roleModel = Utils::getRoleModel();
        /** @var Model $permissionModel */
        $permissionModel = Utils::getPermissionModel();

        $tenancyEnabled = false;
        $teamForeignKey = 'team_id';

        foreach ($rolePlusPermissions as $rolePlusPermission) {
            $tenantId = $rolePlusPermission[$teamForeignKey] ?? null;

            // Set tenant context for role creation and permission sync
            if ($tenancyEnabled) {
                setPermissionsTeamId($tenantId);
            }

            $roleData = [
                'name'       => $rolePlusPermission['name'],
                'guard_name' => $rolePlusPermission['guard_name'],
            ];

            // Include tenant ID in role data (can be null for global roles)
            if ($tenancyEnabled && !blank($teamForeignKey)) {
                $roleData[$teamForeignKey] = $tenantId;
            }

            $role = $roleModel::firstOrCreate($roleData);

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

    public static function makeDirectPermissions(array $permissions): void
    {
        /** @var Model $permissionModel */
        $permissionModel = Utils::getPermissionModel();

        foreach ($permissions as $permission) {
            if ($permissionModel::whereName($permission['name'])->doesntExist()) {
                $permissionModel::create([
                    'name'       => $permission['name'],
                    'guard_name' => $permission['guard_name'],
                ]);
            }
        }
    }
}

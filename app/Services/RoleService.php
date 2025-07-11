<?php

namespace App\Services;

use App\Contracts\Service;
use App\Models\Role;

class RoleService extends Service
{
    public function __construct() {}

    /**
     * Update a role with the given attributes
     */
    public function updateRole(Role $role, array $attrs): Role
    {
        $role->update($attrs);
        $role->save();

        return $role;
    }

    public function setPermissionsForRole(Role $role, array $permissions): void
    {
        // Update the permissions, filter out null/invalid values
        $perms = collect($permissions)->reject(static fn ($v, $k): bool => empty($v));

        $role->permissions()->sync($perms);
    }
}

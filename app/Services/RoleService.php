<?php

namespace App\Services;

use App\Contracts\Service;
use App\Models\Role;

class RoleService extends Service
{
    /**
     * Update a role with the given attributes
     *
     *
     * @return Role
     */
    public function updateRole(Role $role, array $attrs)
    {
        $role->update($attrs);
        $role->save();

        return $role;
    }

    public function setPermissionsForRole(Role $role, array $permissions)
    {
        // Update the permissions, filter out null/invalid values
        $perms = collect($permissions)->filter(static function ($v, $k) {
            return !empty($v);
        });

        $role->permissions()->sync($perms);
    }
}

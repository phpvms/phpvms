<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Role;
use App\Models\User;

/**
 * The 7.x laratrust @ability(roles, permissions) check, answered with the 8.x permissions.
 *
 * @deprecated use @can with the 8.x permission names, or @can('access_admin')
 */
final class LegacyAbility
{
    // 7.x permission => the 8.x abilities it was imported as (import_roles_and_permissions).
    private const array PERMISSIONS = [
        'admin-access' => ['access_admin'],
        'addons'       => ['view:modules'],
        'aircraft'     => ['view:aircraft', 'edit:aircraft', 'delete:aircraft'],
        'airlines'     => ['view:airline', 'edit:airline', 'delete:airline'],
        'airports'     => ['view:airport', 'edit:airport', 'delete:airport'],
        'awards'       => ['view:award', 'edit:award', 'delete:award'],
        'expenses'     => ['view:expense', 'edit:expense', 'delete:expense'],
        'fares'        => ['view:fare', 'edit:fare', 'delete:fare'],
        'finances'     => ['view:finances'],
        'flights'      => ['view:flight', 'edit:flight', 'delete:flight'],
        'maintenance'  => ['view:maintenance'],
        'modules'      => ['view:modules'],
        'pages'        => ['view:page', 'edit:page', 'delete:page'],
        'pireps'       => ['view:pirep', 'edit:pirep', 'delete:pirep', 'view:pirep-field', 'edit:pirep-field', 'delete:pirep-field'],
        'ranks'        => ['view:rank', 'edit:rank', 'delete:rank'],
        'settings'     => ['view:settings', 'edit:settings'],
        'subfleets'    => ['view:subfleet', 'edit:subfleet', 'delete:subfleet'],
        'typeratings'  => ['view:typerating', 'edit:typerating', 'delete:typerating'],
        'users'        => ['view:user', 'edit:user', 'delete:user', 'view:user-field', 'edit:user-field', 'delete:user-field'],
    ];

    /**
     * @param string|list<string> $roles
     * @param string|list<string> $permissions
     */
    public static function check(?User $user, string|array $roles, string|array $permissions = []): bool
    {
        if (!$user instanceof User) {
            return false;
        }

        $abilities = [];
        foreach (self::split($permissions) as $permission) {
            array_push($abilities, ...self::PERMISSIONS[$permission] ?? []);
        }

        // A permission 7.x never shipped (themes invent names like admin-user) has no 8.x
        // equivalent, so only then is the role list the check.
        if ($abilities === []) {
            return $user->hasAnyRole([Role::superAdminName(), ...self::split($roles)]);
        }

        return $user->canAny($abilities);
    }

    /**
     * @param  string|list<string> $value
     * @return list<string>
     */
    private static function split(string|array $value): array
    {
        $items = is_array($value) ? $value : preg_split('/[|,]/', $value);

        return array_values(array_filter(array_map(trim(...), $items), static fn (string $item): bool => $item !== ''));
    }
}

<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Super admin role
    |--------------------------------------------------------------------------
    |
    | The name of the role that bypasses every permission check. A user holding
    | this role is granted all abilities via a `Gate::before` hook registered in
    | AppServiceProvider.
    |
    */

    'super_admin' => 'super_admin',

    /*
    |--------------------------------------------------------------------------
    | Default guard
    |--------------------------------------------------------------------------
    |
    | The guard used when creating roles and permissions.
    |
    */

    'guard' => 'web',

    /*
    |--------------------------------------------------------------------------
    | Custom permissions
    |--------------------------------------------------------------------------
    |
    | Permissions that are not tied to a Filament resource or page. These are
    | grouped for display in the roles permission matrix. Modules and third
    | parties may register additional custom permissions at runtime via
    | app(PermissionRegistry::class)->register(...).
    |
    | Format: 'Group label' => ['permission-name' => 'Human label', ...]
    |
    */

    'custom_permissions' => [
        'Developers' => [
            'view-logs' => 'View Logs',
        ],
        // Backup action permissions are contributed by the Backups page so they
        // appear in the same group as its `view` permission.
    ],

];

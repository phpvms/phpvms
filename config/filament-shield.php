<?php

return [
    'shield_resource' => [
        'should_register_navigation' => true,
        'slug'                       => 'shield/roles',
        'navigation_sort'            => -1,
        'navigation_badge'           => false,
        'navigation_group'           => false,
        'is_globally_searchable'     => true,
        'show_model_path'            => true,
        'is_scoped_to_tenant'        => true,
    ],

    'auth_provider_model' => [
        'fqcn' => \App\Models\User::class,
    ],

    'super_admin' => [
        'enabled'         => true,
        'name'            => 'super_admin',
        'define_via_gate' => true,
        'intercept_gate'  => 'before', // after
    ],

    'panel_user' => [
        'enabled' => false,
        'name'    => 'admin_access',
    ],

    'permission_prefixes' => [
        'resource' => [
            'view',
            'view_any',
            'create',
            'update',
            'restore',
            'restore_any',
            'replicate',
            'reorder',
            'delete',
            'delete_any',
            'force_delete',
            'force_delete_any',
        ],

        'page'   => 'page',
        'widget' => 'widget',
    ],

    'entities' => [
        'pages'              => true,
        'widgets'            => true,
        'resources'          => true,
        'custom_permissions' => true,
    ],

    'generator' => [
        'option'           => 'policies_and_permissions',
        'policy_namespace' => 'Policies\\Filament',
        'policy_directory' => 'Policies\\Filament',
    ],

    'exclude' => [
        'enabled' => true,

        'pages' => [],

        'widgets' => [
            'AccountWidget', 'FilamentInfoWidget',
        ],

        'resources' => [
            'RoleResource',
        ],
    ],

    'discovery' => [
        'discover_all_resources' => true,
        'discover_all_widgets'   => true,
        'discover_all_pages'     => true,
    ],

    'register_role_policy' => [
        'enabled' => true,
    ],

];

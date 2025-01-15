<?php

return [
    'default'           => 'vms',
    'version_installed' => '',

    'repository_types' => [
        'github' => [
            'type'                 => 'github',
            'repository_vendor'    => 'nabeelio',
            'repository_name'      => 'phpvms',
            'repository_url'       => 'https://github.com/nabeelio/phpvms',
            'download_path'        => storage_path('app'),
            'private_access_token' => '',
            'use_branch'           => '',
        ],
    ],

    'mail_to' => [
        'address'                  => 'no-reply@phpvms.net',
        'name'                     => 'no name',
        'subject_update_available' => 'Update available',
        'subject_update_succeeded' => 'Update succeeded',
    ],

    'exclude_folders' => [
        'node_modules',
        'bootstrap/cache',
        'bower',
        'storage/app',
        'storage/framework',
        'storage/logs',
        'storage/self-update',
    ],

    'log_events' => true,

    /*
    |---------------------------------------------------------------------------
    | Register custom artisan commands
    |---------------------------------------------------------------------------
    */

    'artisan_commands' => [
        'pre_update' => [
            // 'command:signature' => [
            //    'class' => Command class
            //    'params' => []
            // ]
        ],
        'post_update' => [

        ],
    ],

];

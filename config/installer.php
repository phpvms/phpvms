<?php

return [
    'php' => [
        'version' => '8.2',
    ],

    'cache' => [
        // Default driver to use when no driver is present
        'default' => 'file',
        'drivers' => [
            // 'Zend OPcache' => 'opcache',
            // 'apc'          => 'apc',
        ],
    ],

    'extensions' => [
        // 'bcmath',
        'fileinfo',
        'openssl',
        'pdo',
        'intl',
        'mbstring',
        'tokenizer',
        'json',
        'curl',
        'dom',
        'zip',
    ],

    // Make sure these are writable
    'permissions' => [
        base_path('bootstrap/cache'),
        public_path('uploads'),
        storage_path(),
        storage_path('app/public'),
        storage_path('app/public/avatars'),
        storage_path('app/public/uploads'),
        storage_path('framework'),
        storage_path('framework/cache'),
        storage_path('framework/sessions'),
        storage_path('framework/views'),
        storage_path('logs'),
    ],
];

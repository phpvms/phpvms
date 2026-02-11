<?php

return [
    'fetch'       => PDO::FETCH_ASSOC,
    'default'     => env('DB_CONNECTION', 'mysql'),
    'connections' => [
        'mysql' => [
            'driver'   => 'mysql',
            'host'     => env('DB_HOST', '127.0.0.1'),
            'port'     => env('DB_PORT', 3306),
            'database' => env('DB_DATABASE', ''),
            'username' => env('DB_USERNAME', ''),
            'password' => env('DB_PASSWORD', ''),
            // 'unix_socket' => env('DB_SOCKET', ''),
            'prefix'         => env('DB_PREFIX', ''),
            'prefix_indexes' => true,
            'timezone'       => '+00:00',
            'charset'        => 'utf8mb4',
            'collation'      => 'utf8mb4_unicode_ci',
            'strict'         => false,
            'engine'         => null,
            'options'        => [
                PDO::ATTR_EMULATE_PREPARES => get_truth_state(env('DB_EMULATE_PREPARES', false)),
            ],
            'dump' => [
                'timeout' => 60 * 5, // 5 minute timeout
            ],
        ],
        'mariadb' => [
            'driver'         => 'mariadb',
            'url'            => env('DB_URL'),
            'host'           => env('DB_HOST', '127.0.0.1'),
            'port'           => env('DB_PORT', '3306'),
            'database'       => env('DB_DATABASE', ''),
            'username'       => env('DB_USERNAME', ''),
            'password'       => env('DB_PASSWORD', ''),
            'unix_socket'    => env('DB_SOCKET', ''),
            'charset'        => env('DB_CHARSET', 'utf8mb4'),
            'collation'      => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix'         => env('DB_PREFIX', ''),
            'prefix_indexes' => true,
            'strict'         => true,
            'engine'         => null,
            'options'        => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],
        'sqlite' => [
            'driver'         => 'sqlite',
            'database'       => env('DB_DATABASE', database_path('database.sqlite')),
            'timezone'       => '+00:00',
            'prefix'         => '',
            'prefix_indexes' => true,
        ],
        'testing' => [
            'driver'         => 'sqlite',
            'database'       => storage_path('testing.sqlite'),
            'timezone'       => '+00:00',
            'prefix'         => '',
            'prefix_indexes' => true,
        ],
        'memory' => [
            'driver'         => 'sqlite',
            'database'       => ':memory:',
            'timezone'       => '+00:00',
            'prefix'         => '',
            'prefix_indexes' => true,
        ],
    ],

    'migrations' => 'migrations',

    'redis' => [
        'cluster' => false,
        'client'  => 'phpredis',
        'default' => [
            'host'     => env('REDIS_HOST', 'localhost'),
            'password' => env('REDIS_PASSWORD', null),
            'port'     => env('REDIS_PORT', 6379),
            'database' => env('REDIS_DATABASE', 1),
        ],
        'cache' => [
            'url'      => env('REDIS_URL'),
            'host'     => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD', null),
            'port'     => env('REDIS_PORT', 6379),
            'database' => env('REDIS_CACHE_DB', 1),
        ],
    ],
];

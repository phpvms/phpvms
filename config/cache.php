<?php

return [
    'default' => env('CACHE_DRIVER', 'array'),
    'prefix'  => env('CACHE_PREFIX', ''),

    'keys' => [
        'AIRPORT_VACENTRAL_LOOKUP' => [
            'key'  => 'airports.lookup:',
            'time' => 60 * 30,
        ],
        'METAR_WEATHER_LOOKUP' => [
            'key'  => 'airports.weather.metar.', // append icao
            'time' => 60 * 60,  // Cache for 60 minutes
        ],
        'RANKS_PILOT_LIST' => [
            'key'  => 'ranks.pilot_list',
            'time' => 60 * 10,
        ],
        'SETTINGS' => [
            'key'  => 'settings.', // append setting key
            'time' => 60 * 60 * 24, // Cache for 1 day
        ],
        'MODULES' => [
            'key'  => 'modules',
            'time' => 60 * 60 * 24, // Cache for 1 day
        ],
        'TAF_WEATHER_LOOKUP' => [
            'key'  => 'airports.weather.taf.', // append icao
            'time' => 60 * 60,  // Cache for 60 minutes
        ],
        'USER_API_KEY' => [
            'key'  => 'user.apikey',
            'time' => 60 * 5,  // 5 min
        ],
    ],

    'stores' => [
        'apc' => [
            'driver' => 'apc',
        ],

        'array' => [
            'driver' => 'array',
        ],

        'database' => [
            'driver'     => 'database',
            'table'      => 'cache',
            'connection' => null,
        ],

        'file' => [
            'driver' => 'file',
            'path'   => storage_path('framework/cache'),
        ],

        'memcached' => [
            'driver'        => 'memcached',
            'persistent_id' => env('MEMCACHED_PERSISTENT_ID'),
            'sasl'          => [
                env('MEMCACHED_USERNAME'),
                env('MEMCACHED_PASSWORD'),
            ],
            'options' => [
                // Memcached::OPT_CONNECT_TIMEOUT  => 2000,
            ],
            'servers' => [
                [
                    'host'   => env('MEMCACHED_HOST', '127.0.0.1'),
                    'port'   => env('MEMCACHED_PORT', 11211),
                    'weight' => 100,
                ],
            ],
        ],

        'opcache' => [
            'driver' => 'opcache',
        ],

        'redis' => [
            'driver'     => 'redis',
            'connection' => 'default',
        ],
    ],
];

<?php

declare(strict_types=1);

return [
    'themes_path'     => resource_path('views/layouts'),
    'asset_not_found' => 'LOG_ERROR',
    'default'         => env('DEFAULT_THEME', 'seven'),
    'cache'           => true,
    'themes'          => [],
    'asset_delivery'  => env('THEME_ASSET_DELIVERY', 'route'),
    'custom_css_max'  => 262144,
    'publish_lock'    => [
        'seconds' => 10,
        'wait'    => 5,
    ],
];

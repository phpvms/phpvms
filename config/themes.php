<?php

return [
    'themes_path'     => resource_path('views/layouts'),
    'asset_not_found' => 'LOG_ERROR',
    'default'         => env('DEFAULT_THEME', 'seven'),
    'cache'           => true,
    'themes'          => [],

];

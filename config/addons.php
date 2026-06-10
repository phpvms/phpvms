<?php

declare(strict_types=1);

return [
    'namespace'            => 'Modules',
    'scan_for_new_on_boot' => true,

    // Maximum size (bytes) for a downloaded addon archive. 0 disables the cap.
    'max_download_bytes' => 100 * 1024 * 1024,

    'paths' => [
        'base'    => base_path('modules'),
        'assets'  => public_path('ext'),
        'staging' => storage_path('app/addon-staging'),
    ],
];

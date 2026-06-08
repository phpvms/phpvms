<?php

declare(strict_types=1);

return [
    'namespace'            => 'Modules',
    'scan_for_new_on_boot' => true,
    'paths'                => [
        'base'   => base_path('modules'),
        'assets' => public_path('ext'),
    ],
];

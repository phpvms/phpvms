<?php

return [
    'default' => 'local',

    // This is the filesystem the uploaded files should go to
    'public_files' => 'public',
    'cloud'        => env('DEFAULT_CLOUD_DISK', 's3'),
    'disks'        => [

        'local' => [
            'driver' => 'local',
            'root'   => storage_path('app'),
        ],

        'seeds' => [
            'driver' => 'local',
            'root'   => database_path('seeds'),
        ],

        'public' => [
            'driver'     => 'local',
            'root'       => public_path('uploads'),
            'url'        => '/uploads',
            'visibility' => 'public',
        ],

        'r2' => [
            'driver'                  => 's3', // R2 is fully compatible with S3 driver
            'region'                  => 'us-east-1', // Region is automatically handled by CloudFlare R2 API
            'key'                     => env('CLOUDFLARE_R2_ACCESS_KEY_ID', ''),
            'secret'                  => env('CLOUDFLARE_R2_SECRET_ACCESS_KEY', ''),
            'bucket'                  => env('CLOUDFLARE_R2_BUCKET', ''),
            'url'                     => env('CLOUDFLARE_R2_URL', ''),
            'endpoint'                => env('CLOUDFLARE_R2_ENDPOINT', ''),
            'use_path_style_endpoint' => env('CLOUDFLARE_R2_USE_PATH_STYLE_ENDPOINT', false),
            'visibility'              => env('CLOUDFLARE_R2_VISIBILITY', 'private'),
            'throw'                   => false,
        ],

        's3' => [
            'driver' => 's3',
            'key'    => env('AMAZON_S3_KEY', ''),
            'secret' => env('AMAZON_S3_SECRET', ''),
            'region' => env('AMAZON_S3_REGION', ''),
            'bucket' => env('AMAZON_S3_BUCKET', ''),
        ],

        'sftp' => [
            'driver'               => 'sftp',
            'host'                 => env('SFTP_HOST', ''),
            'username'             => env('SFTP_USERNAME', ''),
            'password'             => env('SFTP_PASSWORD', ''),
            'privateKey'           => env('SFTP_PRIVATE_KEY', ''),
            'visibility'           => env('SFTP_FILE_VISIBILITY', 'private'), // `private` = 0600, `public` = 0644
            'directory_visibility' => env('SFTP_FOLDER_VISIBILITY', 'private'), // `private` = 0700, `public` = 0755
            'hostFingerprint'      => env('SFTP_HOST_FINGERPRINT', ''),
            'passphrase'           => env('SFTP_PASSPHRASE', ''),
            'port'                 => env('SFTP_PORT', 22),
            'root'                 => env('SFTP_ROOT', ''),
            // Optional SFTP Settings...
            // 'maxTries' => 4,
            // 'timeout' => 30,
            // 'useAgent' => true,
        ],
    ],
];

<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root'   => storage_path('app/private'),
            'serve'  => true,
            'throw'  => false,
            'report' => false,
        ],

        'seeds' => [
            'driver' => 'local',
            'root'   => database_path('seeders'),
        ],

        'public' => [
            'driver'     => 'local',
            'root'       => storage_path('app/public'),
            'url'        => rtrim((string) env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw'      => false,
            'report'     => false,
        ],

        's3' => [
            'driver'                  => 's3',
            'key'                     => env('AWS_ACCESS_KEY_ID'),
            'secret'                  => env('AWS_SECRET_ACCESS_KEY'),
            'region'                  => env('AWS_DEFAULT_REGION'),
            'bucket'                  => env('AWS_BUCKET'),
            'url'                     => env('AWS_URL'),
            'endpoint'                => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw'                   => false,
            'report'                  => false,
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

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];

<?php

declare(strict_types=1);

use App\Services\Metar\AviationWeather;

/**
 * DO NOT MODIFY THIS FILE DIRECTLY!
 * Use your .env instead
 */

return [
    /*
     * Check for if we're "installed" or not
     */
    'installed' => env('PHPVMS_INSTALLED', false),

    /*
     * Avatar resize settings
     * feel free to edit the following lines.
     * Both parameters are in px.
     */
    'avatar' => [
        'width'        => '200',
        'height'       => '200',
        'gravatar_url' => 'https://www.gravatar.com/avatar/',
        'default'      => env('GRAVATAR_DEFAULT_AVATAR', 'https://en.gravatar.com/userimage/12856995/aa6c0527a723abfd5fb9e246f0ff8af4.png'),
    ],

    /*
     * Where to redirect after logging in
     */
    'login_redirect' => '/dashboard',

    /*
     * Where to redirect after registration
     */
    'registration_redirect' => '/profile',

    /*
     * Point to the class to use to retrieve the METAR string. If this
     * goes inactive at some date, it can be replaced
     */
    'metar_lookup' => AviationWeather::class,

    /*
     * URL for where to lookup the Simbrief flight plans
     */
    'simbrief_url' => 'https://www.simbrief.com/ofp/flightplans/xml/{id}.xml',

    /*
     * URL for fetching an updated Simbrief flight plan via API
     */
    'simbrief_update_url' => 'https://www.simbrief.com/api/xml.fetcher.php?userid={sb_user_id}&static_id={sb_static_id}',

    /*
     * URL for fetching Simbrief aircraft and airframe data
     */
    'simbrief_airframes_url' => 'http://www.simbrief.com/api/inputs.airframes.json',

    /*
     * URL for fetching Simbrief layouts data
     */
    'simbrief_layouts_url' => 'http://www.simbrief.com/api/inputs.list.json',

    /*
     * URL for fetching SimBrief OFP
     */
    'simbrief_ofp_url' => 'https://www.simbrief.com/api/xml.fetcher.php',

    /*
     * Your vaCentral API key
     */
    'vacentral_api_key' => env('VACENTRAL_API_KEY', ''),

    /*
     * vaCentral API URL. You likely don't need to change this
     */
    'vacentral_api_url' => 'https://api.vacentral.net',

    /*
     * Misc Settings
     */
    'news_feed_url' => 'http://forum.phpvms.net/rss/1-announcements-feed.xml/?',

    /*
     * URL to the latest version file
     */
    'version_file' => 'https://api.github.com/repos/nabeelio/phpvms/releases',

    /**
     * The URL to download the latest phpVMS version from
     */
    'distrib_url' => 'http://downloads.phpvms.net/phpvms-{VERSION}.zip',

    /**
     * The URL to download the latest TLD list
     */
    'tld_list_url' => 'https://publicsuffix.org/list/public_suffix_list.dat',

    /*
     * Where the KVP file is stored
     */
    'kvp_storage_path' => storage_path('app/kvp.json'),

    /*
     * DO NOT CHANGE THESE! It will result in messed up data
     * The setting you're looking for is in the admin panel,
     * under settings, for the display units
     */
    'internal_units' => [
        'altitude'    => 'feet',
        'distance'    => 'nmi',
        'fuel'        => 'lbs',
        'mass'        => 'lbs',
        'temperature' => 'celsius',
        'velocity'    => 'knots',
        'volume'      => 'gallons',
    ],

    /*
     * DO NOT CHANGE THIS. This is used to map error codes to the approriate
     * RFC 7807 type, which can be used as a machine-readable error code/map
     */
    'error_root' => 'https://phpvms.net/errors',

    /**
     * The links to various docs on the documentation site
     */
    'docs' => [
        'root'             => 'https://docs.phpvms.net',
        'cron'             => '/installation/cron',
        'finances'         => '/concepts/finances',
        'importing_legacy' => '/installation/importing',
        'load_factor'      => '/operations/flights#load-factor',
        'subfleets'        => '/concepts/basics#subfleets-and-aircraft',
        'installation'     => '/installation',
    ],

    /*
     * Enable/disable email verification on registration
     */
    'registration' => [
        'email_verification' => env('EMAIL_VERIFICATION_REQUIRED', true),
    ],

    /*
     * Whether to use prefetching in the admin panel (can use a lot of bandwidth)
     */
    'use_prefetching_in_admin' => env('USE_PREFETCHING_IN_ADMIN', false),

    /**
     * Whether to use the built-in filament import system (relies on laravel queue worker)
     */
    'use_queued_filament_imports' => env('USE_QUEUED_FILAMENT_IMPORTS', false),

    /**
     * Run jobs in cron. Should be enabled when you don't have a queue worker (ie not running the queue:work command). NOT RECOMMENDED
     */
    'run_queued_jobs_in_cron' => env('RUN_QUEUED_JOBS_IN_CRON', false),

    /*
     * Default pagination limits for paginated endpoints. Replaces the
     * removed `config/repository.php` (Prettus l5-repository) defaults.
     *
     * `limit` is the default page size when `?limit=` is not provided.
     * `max`   is the hard cap on `?limit=` query input across every
     *         paginated controller; values above this are clamped to
     *         protect the API from oversized result sets.
     */
    'pagination' => [
        'limit' => env('PHPVMS_PAGINATION_LIMIT', 50),
        'max'   => env('PHPVMS_PAGINATION_MAX', 100),
    ],

    /**
     * Installer related config
     */
    'installer' => [
        'php_version' => '8.3',

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
    ],

    /**
     * RouteForge default values for NULL capability columns
     */
    'routeforge' => [
        'cruise_speed_kt'      => 450,
        'climb_descent_buffer' => 20,
        'turnaround_minutes'   => 60,
        'mesh_warn_count'      => 50,
        'mesh_max_count'       => 100,
    ],

    /**
     * Available languages
     */
    'languages' => [
        // First in the list is the default
        'en' => [
            'display'   => 'English',
            'flag-icon' => 'us',
        ],
        'de' => [
            'display'   => 'German',
            'flag-icon' => 'de',
        ],
        'es-es' => [
            'display'   => 'Spanish (Spain)',
            'flag-icon' => 'es',
        ],
        'fr' => [
            'display'   => 'French',
            'flag-icon' => 'fr',
        ],
        'it' => [
            'display'   => 'Italian',
            'flag-icon' => 'it',
        ],
        'pt-br' => [
            'display'   => 'Portuguese (Brazilian)',
            'flag-icon' => 'br',
        ],
        'jp' => [
            'display'   => 'Japanese (日本語)',
            'flag-icon' => 'jp',
        ],
        'tr' => [
            'display'   => 'Turkish (Türkçe)',
            'flag-icon' => 'tr',
        ],
    ],

    /*
     * This can really be any METAR service, as long as it returns GeoJSON
     */
    'metar_wms' => [
        'url'    => 'https://ogcie.iblsoft.com/observations?',
        'params' => [
            'layers' => 'metar',
        ],
    ],
];

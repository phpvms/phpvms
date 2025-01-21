<?php

/**
 * DO NOT MODIFY THIS FILE DIRECTLY!
 * It will just make upgrading (a little) more difficult
 *
 * Create a local.conf.php in the root (in the same directory
 * as your .env file), and then copy this file into there, and
 * make your changes there.
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
        'width'  => '200',
        'height' => '200',
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
    'metar_lookup' => App\Services\Metar\AviationWeather::class,

    /*
     * Point to the class used to retrieve the airport information.
     * If this goes inactive at some date, it can be replaced
     */
    'airport_lookup' => App\Services\AirportLookup\VaCentralLookup::class,

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
    ],

    /*
     * Enable/disable email verification on registration
     */
    'registration' => [
        'email_verification' => env('EMAIL_VERIFICATION_REQUIRED', true),
    ],
];

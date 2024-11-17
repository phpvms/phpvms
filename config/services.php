<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain'   => env('MAILGUN_DOMAIN'),
        'secret'   => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key'    => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'discord' => [
        'enabled'       => env('DISCORD_OAUTH_ENABLED', false),
        'client_id'     => env('DISCORD_CLIENT_ID'),
        'client_secret' => env('DISCORD_CLIENT_SECRET'),
        'scopes'        => env('DISCORD_SCOPES', '') === '' ? [] : explode(',', env('DISCORD_SCOPES', '')),
        'redirect'      => '/oauth/discord/callback',

        // optional
        'bot_token'                => env('DISCORD_BOT_TOKEN', null),
        'allow_gif_avatars'        => (bool) env('DISCORD_AVATAR_GIF', true),
        'avatar_default_extension' => env('DISCORD_EXTENSION_DEFAULT', 'png'), // only pick from jpg, png, webp
    ],

    'vatsim' => [
        'enabled'       => env('VATSIM_OAUTH_ENABLED', false),
        'client_id'     => env('VATSIM_CLIENT_ID'),
        'client_secret' => env('VATSIM_CLIENT_SECRET'),
        'scopes'        => env('VATSIM_SCOPES', '') === '' ? [] : explode(',', env('VATSIM_SCOPES', '')),
        'redirect'      => '/oauth/vatsim/callback',

        // For local development only
        'test' => env('VATSIM_TEST', false),
    ],

    'ivao' => [
        'enabled'       => env('IVAO_OAUTH_ENABLED', false),
        'client_id'     => env('IVAO_CLIENT_ID'),
        'client_secret' => env('IVAO_CLIENT_SECRET'),
        'scopes'        => env('IVAO_SCOPES', '') === '' ? [] : explode(',', env('IVAO_SCOPES', '')),
        'redirect'      => '/oauth/ivao/callback',
    ],
];

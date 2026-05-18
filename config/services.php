<?php

declare(strict_types=1);

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

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key'    => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel'              => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'discord' => [
        'enabled'       => env('DISCORD_OAUTH_ENABLED', false),
        'client_id'     => env('DISCORD_CLIENT_ID'),
        'client_secret' => env('DISCORD_CLIENT_SECRET'),
        'scopes'        => env('DISCORD_SCOPES', '') === '' ? [] : explode(',', (string) env('DISCORD_SCOPES', '')),
        'redirect'      => '/oauth/discord/callback',

        // optional
        'bot_token'                => env('DISCORD_BOT_TOKEN'),
        'allow_gif_avatars'        => (bool) env('DISCORD_AVATAR_GIF', true),
        'avatar_default_extension' => env('DISCORD_EXTENSION_DEFAULT', 'png'), // only pick from jpg, png, webp
    ],

    'vatsim' => [
        'enabled'       => env('VATSIM_OAUTH_ENABLED', false),
        'client_id'     => env('VATSIM_CLIENT_ID'),
        'client_secret' => env('VATSIM_CLIENT_SECRET'),
        'scopes'        => env('VATSIM_SCOPES', '') === '' ? [] : explode(',', (string) env('VATSIM_SCOPES', '')),
        'redirect'      => '/oauth/vatsim/callback',

        // For local development only
        'test' => env('VATSIM_TEST', false),
    ],

    'ivao' => [
        'enabled'       => env('IVAO_OAUTH_ENABLED', false),
        'client_id'     => env('IVAO_CLIENT_ID'),
        'client_secret' => env('IVAO_CLIENT_SECRET'),
        'scopes'        => env('IVAO_SCOPES', '') === '' ? [] : explode(',', (string) env('IVAO_SCOPES', '')),
        'redirect'      => '/oauth/ivao/callback',
    ],

    'openaip' => [
        // OpenAIP airspace + nav-aid tile overlay. Free key from
        // https://www.openaip.net/users/clients (requires account).
        // Empty key = overlay is silently disabled, base map still renders.
        'api_key' => env('OPENAIP_API_KEY', ''),
    ],
];

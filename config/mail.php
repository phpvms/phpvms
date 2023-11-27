<?php

return [
    'default' => env('MAIL_MAILER', 'smtp'),
    'mailers' => [
        'smtp' => [
            'transport'         => 'smtp',
            'host'              => env('MAIL_HOST', 'smtp.mailgun.org'),
            'port'              => env('MAIL_PORT', 587),
            'encryption'        => env('MAIL_ENCRYPTION', 'tls'),
            'username'          => env('MAIL_USERNAME'),
            'password'          => env('MAIL_PASSWORD'),
            'timeout'           => null,
            'verify_peer'       => env('MAIL_VERIFY_PEER', true),
            'verify_peer_name'  => env('MAIL_VERIFY_PEER_NAME', true),
            'allow_self_signed' => env('MAIL_ALLOW_SELF_SIGNED', false),
        ],

        'ses' => [
            'transport' => 'ses',
        ],

        'mailgun' => [
            'transport' => 'mailgun',
        ],

        'postmark' => [
            'transport' => 'postmark',
        ],

        'sendmail' => [
            'transport' => 'sendmail',
            'path'      => '/usr/sbin/sendmail -bs',
        ],

        'log' => [
            'transport' => 'log',
            'channel'   => env('MAIL_LOG_CHANNEL', 'stack'),
        ],

        'array' => [
            'transport' => 'array',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Global "From" Address
    |--------------------------------------------------------------------------
    |
    | You may wish for all e-mails sent by your application to be sent from
    | the same address. Here, you may specify a name and address that is
    | used globally for all e-mails that are sent by your application.
    |
    */

    'from' => [
        'name'    => env('MAIL_FROM_NAME', 'phpVMS Admin'),
        'address' => env('MAIL_FROM_ADDRESS', 'no-reply@phpvms.net'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Markdown Mail Settings
    |--------------------------------------------------------------------------
    |
    | If you are using Markdown based email rendering, you may configure your
    | theme and component paths here, allowing you to customize the design
    | of the emails. Or, you may simply stick with the Laravel defaults!
    |
    */

    'markdown' => [
        'theme' => 'default',
        'paths' => [
            resource_path('views/vendor/mail'),
        ],
    ],
];

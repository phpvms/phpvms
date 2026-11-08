<?php

declare(strict_types=1);

return [
    401 => [
        'title'   => 'Unauthorized Access',
        'message' => 'Well, this is embarrassing, you are not authorized to access or perform this function. '.
            'Click <a href=":link">here</a> to go back to the home page.',
    ],
    404 => [
        'title'   => 'Page Not Found',
        'message' => 'Well, this is embarrassing, the page you requested does not exist.'.
            'Click <a href=":link">here</a> to go back to the home page.',
    ],
    500 => [
        'title'   => 'Server Error',
        'message' => 'Something went wrong on our side. If you are a pilot, tell your webmaster what you did to get here.',
    ],
    503 => [
        'title'   => 'Internal Error',
        'message' => 'An Error Occured',
    ],
    'database_unreachable' => [
        'title'   => 'Temporarily unavailable',
        'message' => 'This site is down right now. Please try again shortly.',
    ],
    'not_installed' => [
        'title'   => 'Not set up yet',
        'message' => 'phpvms is not set up yet. If you run this site, open the installer to finish setup.',
        'link'    => 'Open the installer',
    ],
];

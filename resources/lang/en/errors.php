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
    503 => [
        'title'   => 'Internal Error',
        'message' => 'An Error Occured',
    ],
];

<?php

declare(strict_types=1);

return [
    401 => [
        'title'   => 'Non Autorizzato',
        'message' => 'Beh, è imbarazzante, non sei autorizzato ad accedere o ad eseguire questa funzionalità. '.
            'Clicca <a href=":link">qui</a> per tornare alla home page.',
    ],
    404 => [
        'title'   => 'Page Not Found',
        'message' => 'Beh, è imbarazzante, la pagina che hai richiesto non esiste.'.
            'Clicca <a href=":link">qui</a> per tornare alla home page.',
    ],
    503 => [
        'title'   => 'Internal Error',
        'message' => 'Torniamo subito.',
    ],
];

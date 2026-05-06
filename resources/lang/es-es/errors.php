<?php

declare(strict_types=1);

return [
    401 => [
        'title'   => 'Acceso no autorizado',
        'message' => 'Bueno, esto es embarazoso, no estás autorizado a acceder o realizar esta acción. '.
            'Clic <a href=":link">aquí</a> para retroceder a la página de inicio.',
    ],
    404 => [
        'title'   => 'Página no encontrada',
        'message' => 'Bueno, esto es embarazoso, la página solicitada no existe.'.
            'Clic <a href=":link">aquí</a> para retroceder a la página de inicio.',
    ],
    503 => [
        'title'   => 'Error interno',
        'message' => 'Ocurrió un error',
    ],
];

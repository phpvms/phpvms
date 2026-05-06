<?php

declare(strict_types=1);

return [
    401 => [
        'title'   => 'Accès Non Autorisé',
        'message' => 'Embarrassant, vous n\'êtes pas autorisé à accéder à cette page ou à exécuter cette fonction. Cliquez <a href=":link">ici</a> pour revenir à la page d\'accueil.',
    ],
    404 => [
        'title'   => 'Page Non Trouvée',
        'message' => 'Embarrassant, la page demandée n\'existe pas. Cliquez <a href=":link">ici</a> pour revenir à la page accueil.',
    ],
    503 => [
        'title'   => 'Erreur Interne',
        'message' => "Une Erreur s'est Produite",
    ],
];

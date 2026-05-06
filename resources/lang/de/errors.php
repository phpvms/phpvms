<?php

declare(strict_types=1);

return [
    401 => [
        'title'   => 'Unberechtigter Zugriff',
        'message' => 'Nun, das ist peinlich, Sie sind nicht berechtigt, auf diese Funktion zuzugreifen oder sie auszuführen. '.
        ' Klicken Sie <a href=":link">hier</a>, um zurück zur Startseite zu gelangen.',
    ],
    404 => [
        'title'   => 'Seite nicht gefunden',
        'message' => 'Nun, das ist peinlich, die von Ihnen angeforderte Seite existiert nicht.'.
        ' Klicken Sie <a href=":link">hier</a>, um zurück zur Startseite zu gelangen.',
    ],
    503 => [
        'title'   => 'Interner Fehler',
        'message' => 'Ein Fehler ist aufgetreten',
    ],
];

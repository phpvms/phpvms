<?php

return [
    /*
     *
     * Shared translations.
     *
     */
    'title'  => 'phpVMS Installateur',
    'next'   => 'Etape suivante',
    'back'   => 'Etape Précédente',
    'finish' => 'Installation',
    'output' => 'Sortie de la console',
    'forms'  => [
        'errorTitle' => 'Les erreurs suivantes se sont produites:',
    ],

    /*
     *
     * Home page translations.
     *
     */
    'welcome' => [
        'templateTitle' => 'Bienvenue',
        'title'         => 'phpVMS Installateur',
        'message'       => 'Assistant installation.',
        'next'          => 'Vérifier les exigences',
    ],

    /*
     *
     * Requirements page translations.
     *
     */
    'requirements' => [
        'templateTitle' => 'Step 1 | Exigences du serveur',
        'title'         => 'Exigences du serveur',
        'next'          => 'Vérifier les permissions',
    ],

    /*
     *
     * Permissions page translations.
     *
     */
    'permissions' => [
        'templateTitle' => 'Step 2 | Permissions',
        'title'         => 'Permissions',
        'next'          => 'Configurer environnement',
    ],

    /*
     *
     * Environment page translations.
     *
     */
    'environment' => [
        'menu' => [
            'templateTitle'  => 'Step 3 | Paramétrage environnement',
            'title'          => 'Paramétrage environnement',
            'desc'           => 'Veuillez choisir comment vous voulez configurer le fichier <code>.env</code> application.',
            'wizard-button'  => 'Configuration assistant de formulaire',
            'classic-button' => 'Éditeur de texte classique',
        ],
        'wizard' => [
            'templateTitle' => 'Step 3 | Paramètres environnement | Assistant guide',
            'title'         => 'Guide <code>.env</code> Assistant',
            'tabs'          => [
                'environment' => 'Environnement',
                'database'    => 'Database',
                'application' => 'Application',
            ],
            'form' => [
                'name_required'                      => 'Un nom environnement est requis.',
                'app_name_label'                     => 'App Nom',
                'app_name_placeholder'               => 'App Nom',
                'app_environment_label'              => 'App Environnement',
                'app_environment_label_local'        => 'Local',
                'app_environment_label_developement' => 'Développement',
                'app_environment_label_qa'           => 'Qa',
                'app_environment_label_production'   => 'Production',
                'app_environment_label_other'        => 'Autre',
                'app_environment_placeholder_other'  => 'Entrez votre environnement...',
                'app_debug_label'                    => 'App Debug',
                'app_debug_label_true'               => 'True',
                'app_debug_label_false'              => 'False',
                'app_log_level_label'                => 'App Log Level',
                'app_log_level_label_debug'          => 'debug',
                'app_log_level_label_info'           => 'info',
                'app_log_level_label_notice'         => 'notice',
                'app_log_level_label_warning'        => 'warning',
                'app_log_level_label_error'          => 'error',
                'app_log_level_label_critical'       => 'critical',
                'app_log_level_label_alert'          => 'alert',
                'app_log_level_label_emergency'      => 'emergency',
                'app_url_label'                      => 'App Url',
                'app_url_placeholder'                => 'App Url',
                'db_connection_label'                => 'Connexion à la base de données',
                'db_connection_label_mysql'          => 'mysql',
                'db_connection_label_sqlite'         => 'sqlite',
                'db_connection_label_pgsql'          => 'pgsql',
                'db_connection_label_sqlsrv'         => 'sqlsrv',
                'db_host_label'                      => 'Database Host',
                'db_host_placeholder'                => 'Database Host',
                'db_port_label'                      => 'Database Port',
                'db_port_placeholder'                => 'Database Port',
                'db_name_label'                      => 'Database Name',
                'db_name_placeholder'                => 'Database Name',
                'db_username_label'                  => 'Database User Name',
                'db_username_placeholder'            => 'Database User Name',
                'db_password_label'                  => 'Database Password',
                'db_password_placeholder'            => 'Database Password',

                'app_tabs' => [
                    'more_info'                => 'More Info',
                    'broadcasting_title'       => 'Broadcasting, Caching, Session, &amp; Queue',
                    'broadcasting_label'       => 'Broadcast Driver',
                    'broadcasting_placeholder' => 'Broadcast Driver',
                    'cache_label'              => 'Cache Driver',
                    'cache_placeholder'        => 'Cache Driver',
                    'session_label'            => 'Session Driver',
                    'session_placeholder'      => 'Session Driver',
                    'queue_label'              => 'Queue Driver',
                    'queue_placeholder'        => 'Queue Driver',
                    'redis_label'              => 'Redis Driver',
                    'redis_host'               => 'Redis Host',
                    'redis_password'           => 'Redis Password',
                    'redis_port'               => 'Redis Port',

                    'mail_label'                  => 'Mail',
                    'mail_driver_label'           => 'Mail Driver',
                    'mail_driver_placeholder'     => 'Mail Driver',
                    'mail_host_label'             => 'Mail Host',
                    'mail_host_placeholder'       => 'Mail Host',
                    'mail_port_label'             => 'Mail Port',
                    'mail_port_placeholder'       => 'Mail Port',
                    'mail_username_label'         => 'Mail Username',
                    'mail_username_placeholder'   => 'Mail Username',
                    'mail_password_label'         => 'Mail Password',
                    'mail_password_placeholder'   => 'Mail Password',
                    'mail_encryption_label'       => 'Mail Encryption',
                    'mail_encryption_placeholder' => 'Mail Encryption',

                    'pusher_label'                  => 'Pusher',
                    'pusher_app_id_label'           => 'Pusher App Id',
                    'pusher_app_id_palceholder'     => 'Pusher App Id',
                    'pusher_app_key_label'          => 'Pusher App Key',
                    'pusher_app_key_palceholder'    => 'Pusher App Key',
                    'pusher_app_secret_label'       => 'Pusher App Secret',
                    'pusher_app_secret_palceholder' => 'Pusher App Secret',
                ],
                'buttons' => [
                    'setup_database'    => 'Configuration de la base de données',
                    'setup_application' => 'Configuration application',
                    'install'           => 'Installation',
                ],
            ],
        ],
        'classic' => [
            'templateTitle' => 'Step 3 | Paramètres environnement | Éditeur classique',
            'title'         => 'Éditeur environnement classique',
            'save'          => 'Savegarde .env',
            'back'          => 'Utiliser assistant de formulaire',
            'install'       => 'Savegarder et Installer',
        ],
        'success' => 'Les paramètres de votre fichier .env ont été enregistrés.',
        'errors'  => 'Impossible enregistrer le fichier .env, veuillez le créer manuellement.',
    ],

    'install' => 'Installation',

    /*
     *
     * Installed Log translations.
     *
     */
    'installed' => [
        'success_log_message' => 'Laravel Installer a été installé avec succès sur ',
    ],

    /*
     *
     * Final page translations.
     *
     */
    'final' => [
        'title'         => 'Installation terminée',
        'templateTitle' => 'Installation terminée',
        'finished'      => 'Cette application a été installée avec succès.',
        'migration'     => 'Migration &amp; Sortie de la console amorçage:',
        'console'       => 'Sortie de la console application:',
        'log'           => 'Entrée du journal installation :',
        'env'           => 'Final .env File:',
        'exit'          => 'Cliquez ici pour sortir',
    ],

    /*
     *
     * Update specific translations
     *
     */
    'updater' => [
        /*
         *
         * Shared translations.
         *
         */
        'title' => 'Outil de mise à jour de phpVMS',

        /*
         *
         * Welcome page translations for update feature.
         *
         */
        'welcome' => [
            'title'   => 'Bienvenue dans assistant de mise à jour',
            'message' => 'Bienvenue dans assistant de mise à jour.',
        ],

        /*
         *
         * Welcome page translations for update feature.
         *
         */
        'overview' => [
            'title'           => 'Vue ensemble',
            'message'         => 'Il y a 1 mise à jour.|Il y a :number de mises à jour.',
            'install_updates' => 'Installer les mises à jour',
        ],

        /*
         *
         * Final page translations.
         *
         */
        'final' => [
            'title'    => 'Terminé',
            'finished' => 'La base de données application a été mise à jour avec succès.',
            'exit'     => 'Cliquez ici pour quitter',
        ],

        'log' => [
            'success_message' => 'Laravel Installer a été mis à jour avec succès sur ',
        ],
    ],
];

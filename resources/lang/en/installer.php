<?php

return [
    /*
     *
     * Shared translations.
     *
     */
    'title'                             => 'phpVMS Installer',
    'next'                              => 'Next Step',
    'back'                              => 'Previous',
    'finish'                            => 'Install',
    'output'                            => 'Console Output',
    'already_installed'                 => 'phpVMS is already installed.',
    'install_completed'                 => 'phpVMS Installation completed successfully.',
    'complete_setup'                    => 'Complete Setup',
    'failed'                            => 'Failed',
    'db_connection_ok'                  => 'Connection OK',
    'db_connection_failed'              => 'Unable to connect to the database. Please check your .env database settings. Exception: :exception',
    'starting_migration_process'        => 'Starting migration process...',
    'migrations_completed'              => 'Migrations completed successfully.',
    'generating_app_key'                => 'Generating new application key...',
    'app_key_warning'                   => 'WARNING: You are still using the default application key. This is not recommended. Please generate a new key by running:',
    'requirements'                      => 'Requirements',
    'create_env'                        => 'The first step is to create a .env file. You can use .env.example as a reference and, more importantly, consult our documentation.',
    'important'                         => 'Important',
    'php_version'                       => 'PHP Version',
    'php_extensions'                    => 'PHP Extensions',
    'extension'                         => 'Extension',
    'directory_permissions'             => 'Directory Permissions',
    'directory_permissions_description' => 'Make sure these directories have read and write permissions.',
    'directory'                         => 'Directory',
    'database'                          => 'Database',
    'database_connection'               => 'Database Connection',
    'requirements_not_met'              => 'Some requirements were not met. Please fix them before continuing.',
    'migrations'                        => 'Migrations',
    'click_update_to_run'               => 'Click "Update" to run the script.',
    'update'                            => 'Update',
    'migrations_not_completed'          => 'You still have :count migrations to run. Please try again...',
    'user_and_airline_setup'            => 'User & Airline Setup',
    'legacy_importer'                   => 'phpVMS v5 Legacy Importer',
    'super_admin_informations'          => 'Super Admin User Informations',
    'lets_rebuild_cache'                => 'Let\'s rebuild the cache.',
    'cache_build_background'            => 'You don\'t have access to the proc_open function, so the cache rebuild will be done in the background. Check logs for details.',
    'update_completed'                  => 'Update completed successfully. You\'ll be redirected in a few seconds',
    'update_phpvms'                     => 'Update phpVMS',
];

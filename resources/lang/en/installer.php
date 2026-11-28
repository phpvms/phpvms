<?php

declare(strict_types=1);

return [
    /*
     *
     * Shared translations.
     *
     */
    'title'                  => 'phpvms',
    'next'                   => 'Next Step',
    'back'                   => 'Previous',
    'finish'                 => 'Install',
    'already_installed'      => 'phpvms is already installed',
    'update_in_progress'     => 'Another install or update is already running. Wait for it to finish, then try again.',
    'bundled_link_failed'    => 'WARNING: Could not link the bundled addons into storage/app/addons. Run "php artisan phpvms:addons-link-bundled" afterwards, or the addon manager and the airside theme will not load.',
    'update_step_failed'     => 'WARNING: A seeding or key-generation step failed. The update will continue; check the logs for details.',
    'generating_app_key'     => 'Generating new application key...',
    'app_key_warning'        => 'WARNING: You are still using the default application key. This is not recommended. Please generate a new key by running:',
    'php_version'            => 'PHP Version',
    'click_update_to_run'    => 'Click "Update" to run the script.',
    'update'                 => 'Update',
    'legacy_importer'        => 'phpvms v5 Legacy Importer',
    'lets_rebuild_cache'     => "Let's rebuild the cache.",
    'cache_build_background' => "You don't have access to the proc_open function, so the cache rebuild will be done in the background. Check logs for details.",
    'update_completed'       => "Update completed successfully. You'll be redirected in a few seconds",
    'update_phpvms'          => 'Update phpvms',
    'upgrade_phpvms'         => 'Upgrade phpvms',
    'updater'                => [
        'warning'           => 'This updates the database to phpvms 8. Back up your database and files first. The site is in maintenance mode while the update runs.',
        'cron_required'     => 'The update runs from cron, and cron has not run in the last five minutes. Add this line to your crontab, then reload this page:',
        'confirm'           => 'I have a backup',
        'start'             => 'Start update',
        'nothing_to_update' => 'There is no update to run.',
        'failed_at'         => 'The update failed at: :step',
        'copy'              => 'Copy',
        'retry'             => 'Retry',
        'log'               => 'Log',
        'done'              => 'The update is complete.',
        'go_to_admin'       => 'Go to admin',
        'theme_deprecated'  => 'Your theme, :theme, still uses 7.x features that are deprecated: :items. They work for now, but will be removed in a later release. Upgrade the theme, or ask its author for an 8.x version.',
        'waiting_for_cron'  => 'Waiting for cron',
        'waiting'           => 'waiting',
        'schedule_paused'   => 'Scheduled tasks have not run since :since because an update run is unfinished. If you fixed the site by hand, delete :file to start them again.',
        'system_updates'    => 'System updates',
        'admin_warning'     => 'This applies pending database updates. Back up your database and files first. The site is in maintenance mode while the update runs.',
        'steps'             => [
            'backup'  => 'Backup',
            'upgrade' => 'Upgrade',
        ],
        'step_labels' => [
            'preflight'        => 'Preflight',
            'maintenance_down' => 'Maintenance Down',
            'innodb'           => 'Converting to InnoDB',
            'cache_tables'     => 'Cache Tables',
            'link_addons'      => 'Link Addons',
            'migrate'          => 'Migrate',
            'seed'             => 'Seed',
            'data_migrate'     => 'Data Migrate',
            'data_tasks'       => 'Migrating Data',
            'passport_keys'    => 'Passport Keys',
            'storage_link'     => 'Storage Link',
            'marker'           => 'Marker',
            'addon_cache'      => 'Addon Cache',
            'optimize'         => 'Optimize',
            'octane_reload'    => 'Octane Reload',
            'maintenance_up'   => 'Maintenance Up',
        ],
    ],
    'post_upgrade' => [
        'tasks' => [
            'pirep_events_backfill' => 'Migrating PIREP Logs',
            'simbrief_ofps'         => 'Migrating SimBrief OFPs',
            'theme_deprecations'    => 'Checking the theme',
        ],
    ],
    'web_installer' => [
        'title'         => 'Install phpvms',
        'cron_required' => 'The last step needs cron, and cron has not run in the last five minutes. Add this line to your crontab. The install finishes on the next run:',
    ],
    'first_user' => [
        'title'                 => 'Create the first admin',
        'password'              => 'Password',
        'password_confirmation' => 'Confirm password',
        'submit'                => 'Create admin',
        'busy'                  => 'Another setup is running. Try again in a moment.',
    ],
    'setup_wizard' => [
        'busy'              => 'Setup is already being saved. Try again in a moment.',
        'title'             => 'Set up your airline',
        'save'              => 'Finish setup',
        'failed'            => 'Setup could not be saved',
        'being_set_up'      => 'This site is being set up',
        'being_set_up_text' => 'Please check back soon.',
    ],
];

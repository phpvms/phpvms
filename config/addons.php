<?php

declare(strict_types=1);

return [
    'namespace'            => 'Modules',
    'scan_for_new_on_boot' => true,

    // Maximum size (bytes) for a downloaded addon archive. 0 disables the cap.
    'max_download_bytes' => 100 * 1024 * 1024,

    // The registry's published addon manifest schema, kept as a local file and
    // re-downloaded once it is older than `ttl` seconds.
    'schema' => [
        'url'  => 'https://addons.phpvms.net/schema/addon.json',
        'path' => storage_path('app/addons/addon.schema.json'),
        'ttl'  => 86400,
    ],

    // Addons that ship with phpvms. Each one stays in modules/<name> and is
    // linked into the addon root as `<root>/<name> -> ../../../modules/<name>`
    // by `phpvms:addons-link-bundled`, so a new release updates it in place.
    // Discovery skips these names in modules/, so each loads once, via its link.
    'bundled' => [
        'phpvms-addon-manager',
        'phpvms-airside-theme',
        'phpvms-awards',
        'phpvms-widgets',
    ],

    'paths' => [
        // Where addons are installed: modules and themes, the bundled links and
        // every addon installed at runtime. Discovery scans it first. Under storage/
        // because that is the persistent writable volume in compose.deploy.yml,
        // so installs survive a redeploy.
        'root' => storage_path('app/addons'),

        // Where the bundled addons' files live. Part of the release, never
        // written to at runtime. Discovery scans it after the root, skipping
        // the bundled names above.
        'bundled' => base_path('modules'),

        // Where each theme addon's public/ is linked, as themes/<name>. Inside
        // storage/app/public so `storage:link` (public/storage) already serves
        // it, and inside the persistent volume so the link survives a redeploy.
        'theme_assets' => storage_path('app/public/themes'),

        'assets'  => public_path('ext'),
        'staging' => storage_path('app/addon-staging'),
        // ADDONS_BOOT_CACHE moves it, as APP_CONFIG_CACHE moves the config
        // cache; a relative value is resolved against the app root. The test
        // suite uses this to boot from its own cache, not the install's.
        'boot_cache' => str_starts_with((string) env('ADDONS_BOOT_CACHE', ''), '/')
            ? (string) env('ADDONS_BOOT_CACHE')
            : base_path((string) env('ADDONS_BOOT_CACHE', 'bootstrap/cache/addons.php')),
    ],
];

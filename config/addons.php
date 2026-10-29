<?php

declare(strict_types=1);

return [
    'namespace'            => 'Modules',
    'scan_for_new_on_boot' => true,

    // Maximum size (bytes) for a downloaded addon archive. 0 disables the cap.
    'max_download_bytes' => 100 * 1024 * 1024,

    'paths' => [
        // Addons shipped inside the deployment image, updated only by deploying
        // a new image. Never written to at runtime: in a container this path is
        // part of the image layer, so a runtime write is either lost on the next
        // deploy or — if a volume is mounted over it — freezes the image's copy
        // forever, which is exactly what the `phpvms_modules` volume used to do.
        'base' => base_path('modules'),

        // Addons installed at runtime through the addon manager. Under storage/
        // because that is already a persistent writable volume in
        // compose.deploy.yml, so installs survive a redeploy without needing a
        // mount of their own. Deliberately NOT under storage/app, which is a
        // filesystem disk root.
        //
        // Must not be nested inside `base` above. Every addon under it would
        // then also match the image root, and the lifecycle would treat all of
        // them as image-managed: updates refused, delete reduced to disable,
        // reinstall blocked by the existing-row check.
        'installed' => storage_path('addons'),

        // Installed addons of type "theme". Same volume and same lifecycle as
        // `installed`, kept apart so themes and modules are not mixed on disk.
        // The same nesting rule applies: not inside `base`. Not storage/themes:
        // `theme:package` and `theme:install` keep their packages and a tmp/
        // directory there.
        'themes' => storage_path('addon-themes'),

        // Where each theme addon's public/ is linked, as themes/<name>. Inside
        // storage/app/public so `storage:link` (public/storage) already serves
        // it, and inside the persistent volume so the link survives a redeploy.
        'theme_assets' => storage_path('app/public/themes'),

        'assets'     => public_path('ext'),
        'staging'    => storage_path('app/addon-staging'),
        'boot_cache' => base_path('bootstrap/cache/addons.php'),
    ],
];

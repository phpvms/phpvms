<?php

use App\Addons\Services\AddonDiscoveryService;
use App\Addons\Support\AddonRoots;
use App\Addons\Support\BundledAddonLinker;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Data migration: addons are found only in the addon root, storage/app/addons.
 * Move what was installed at runtime under the old roots, storage/addons and
 * storage/addon-themes, into it, and link the bundled addons in.
 *
 * The old paths are named here because they are the layout being moved away
 * from; nothing reads them any more. A directory whose name is already taken
 * in the root is left where it is and logged. Discovery then re-points each
 * row at its new path by registry_id. Safe to run again.
 */
return new class() extends Migration
{
    public function up(): void
    {
        $root = AddonRoots::root();
        File::ensureDirectoryExists($root);

        foreach ([storage_path('addons'), storage_path('addon-themes')] as $old) {
            if (!is_dir($old) || realpath($old) === realpath($root)) {
                continue;
            }

            foreach (scandir($old) ?: [] as $name) {
                if (str_starts_with($name, '.') || !is_dir($old.'/'.$name)) {
                    continue;
                }

                $dest = $root.'/'.$name;

                if (file_exists($dest) || is_link($dest)) {
                    Log::warning(sprintf('Addon "%s" not moved from %s: %s already exists.', $name, $old, $dest));

                    continue;
                }

                // rename() cannot cross a mount point; copy and delete then.
                if (!@rename($old.'/'.$name, $dest) && File::copyDirectory($old.'/'.$name, $dest)) {
                    File::deleteDirectory($old.'/'.$name);
                }
            }

            if (array_diff(scandir($old) ?: [], ['.', '..']) === []) {
                @rmdir($old);
            }
        }

        app(BundledAddonLinker::class)->link();

        if (Schema::hasTable('addons')) {
            $discovery = app(AddonDiscoveryService::class);
            $discovery->discoverNewAddons();
            $discovery->rebuildCache();
        }
    }
};

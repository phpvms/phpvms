<?php

use App\Features\Addons\Services\AddonDiscoveryService;
use App\Features\Addons\Support\AddonRoots;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * Data migration: addon.json is the manifest name, so rename module.json to
 * addon.json in every installed addon directory. module.json is still read.
 *
 * A directory that already has addon.json is left alone. Safe to run again.
 *
 * Awards and widgets are skipped: they are bundled, so their root entries are
 * links into modules/, and a rename would edit the release's files.
 */
return new class() extends Migration
{
    /**
     * @var list<string>
     */
    private const array RETIRED = ['phpvms/awards', 'phpvms/widgets'];

    public function up(): void
    {
        $renamed = false;

        $root = AddonRoots::root();

        foreach (is_dir($root) ? File::directories($root) : [] as $dir) {
            $legacy = $dir.'/module.json';

            if (is_file($legacy) && !file_exists($dir.'/addon.json') && !$this->isRetired($legacy)) {
                $renamed = rename($legacy, $dir.'/addon.json') || $renamed;
            }
        }

        if ($renamed && Schema::hasTable('addons')) {
            app(AddonDiscoveryService::class)->rebuildCache();
        }
    }

    private function isRetired(string $manifest): bool
    {
        $decoded = json_decode((string) file_get_contents($manifest), true);

        return is_array($decoded) && in_array($decoded['registry_id'] ?? null, self::RETIRED, true);
    }
};

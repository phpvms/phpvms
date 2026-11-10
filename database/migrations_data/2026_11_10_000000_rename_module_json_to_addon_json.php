<?php

use App\Addons\Services\AddonDiscoveryService;
use App\Addons\Support\AddonRoots;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * Data migration: core reads addon.json only, so rename module.json to
 * addon.json in every installed addon directory.
 *
 * A directory that already has addon.json is left alone. Safe to run again.
 *
 * Awards and widgets keep module.json so they stop loading: Awards is moving out of
 * core and its legacy awards become rule-based (VMS-200), and widgets move into
 * app/Features/Widgets.
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

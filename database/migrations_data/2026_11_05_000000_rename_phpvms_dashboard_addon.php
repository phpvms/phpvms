<?php

use App\Addons\Services\AddonDiscoveryService;
use App\Addons\Support\AddonAssetLinker;
use App\Models\Addon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Data migration: the bundled `phpvms/dashboard` addon is now `phpvms/widgets`
 * (modules/phpvms-dashboard → modules/phpvms-widgets).
 *
 * The new addon is discovered and, when the old one was enabled, its row is
 * enabled directly. AddonRegistry::enable() is not used: it reloads Octane in
 * the middle of the migration. Then the old asset link is removed and, last,
 * the old row deleted. A failure part-way leaves the old row, so a retry runs
 * every step again.
 */
return new class() extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('addons')) {
            return;
        }

        $old = Addon::query()->where('registry_id', 'phpvms/dashboard')->first();
        if ($old === null) {
            return;
        }

        $discovery = app(AddonDiscoveryService::class);
        $discovery->discoverNewAddons();

        if ($old->enabled) {
            Addon::query()->where('registry_id', 'phpvms/widgets')->update(['enabled' => true]);
        }

        app(AddonAssetLinker::class)->unlink('PhpvmsDashboard');
        $old->delete();

        $discovery->rebuildCache();
    }
};

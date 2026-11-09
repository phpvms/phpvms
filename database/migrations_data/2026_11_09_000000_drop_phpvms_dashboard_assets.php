<?php

use App\Addons\Services\AddonDiscoveryService;
use App\Addons\Support\AddonAssetLinker;
use App\Models\Addon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Data migration: finish the `phpvms/dashboard` → `phpvms/widgets` rename.
 *
 * The rename migration moves the row but leaves the old `phpvmsdashboard` asset
 * link and a boot cache that still lists the deleted module, so both are
 * cleaned up here. Each step is safe to run again.
 */
return new class() extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('addons')) {
            return;
        }

        app(AddonAssetLinker::class)->unlink('PhpvmsDashboard');

        if (Addon::query()->where('registry_id', 'phpvms/widgets')->exists()) {
            app(AddonDiscoveryService::class)->rebuildCache();
        }
    }
};

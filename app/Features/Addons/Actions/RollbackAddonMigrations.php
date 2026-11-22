<?php

declare(strict_types=1);

namespace App\Features\Addons\Actions;

use App\Models\Addon;
use Illuminate\Support\Facades\Artisan;

/**
 * Runs every one of an addon's migrations down, dropping its tables.
 */
final readonly class RollbackAddonMigrations
{
    /**
     * @param string|null $addonPath where the files are, when moved from `$addon->getPath()`
     */
    public function handle(Addon $addon, ?string $addonPath = null): void
    {
        $path = ($addonPath ?? $addon->getPath()).'/database/migrations';

        if (!is_dir($path)) {
            return;
        }

        Artisan::call('migrate:reset', [
            '--force'    => true,
            '--realpath' => true,
            '--path'     => [$path],
        ]);
    }
}

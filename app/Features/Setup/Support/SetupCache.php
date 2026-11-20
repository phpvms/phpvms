<?php

declare(strict_types=1);

namespace App\Features\Setup\Support;

use App\Features\Setup\Enums\RunStatus;

/**
 * Whether an install or update is in progress, read from files only: the cache store may not exist yet.
 */
final class SetupCache
{
    /** True while this process builds a config cache, so the setup store is not baked into it. */
    public static bool $buildingConfig = false;

    public static function active(): bool
    {
        if (!is_file(UpgradeCompleted::defaultPath())) {
            return true;
        }

        if (!is_file(storage_path('framework/down'))) {
            return false;
        }

        $path = SetupRunState::defaultPath();

        if (!is_file($path)) {
            return false;
        }

        $raw = @file_get_contents($path);
        $data = $raw === false ? null : json_decode($raw, true);

        // A state file that cannot be read is treated as a run in progress.
        if (!is_array($data) || !is_string($data['status'] ?? null)) {
            return true;
        }

        return in_array($data['status'], [RunStatus::Requested->value, RunStatus::Running->value, RunStatus::Failed->value], true);
    }
}

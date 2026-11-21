<?php

declare(strict_types=1);

namespace App\Features\Setup\Support;

use App\Features\Setup\Enums\RunStatus;
use App\Features\Setup\Enums\SetupStep;
use App\Features\Setup\Enums\StepStatus;

/**
 * Whether an install or update is in progress, read from files only: the cache store may not exist yet.
 */
final class SetupCache
{
    /** True while this process builds a config cache, so the setup store is not baked into it. */
    public static bool $buildingConfig = false;

    /** The configured store while this process uses the array one, so setup can still clear it. */
    public static ?string $realStore = null;

    public static function active(): bool
    {
        $path = SetupRunState::defaultPath();
        $raw = is_file($path) ? @file_get_contents($path) : null;
        $data = is_string($raw) ? json_decode($raw, true) : null;

        // Once the run has brought the site up, its cache tables exist; workers reloaded after use the real store.
        if (is_array($data) && ($data['steps'][SetupStep::MaintenanceUp->value]['status'] ?? null) === StepStatus::Done->value) {
            return false;
        }

        if (!is_file(UpgradeCompleted::defaultPath())) {
            return true;
        }

        if (!is_file(storage_path('framework/down')) || $raw === null) {
            return false;
        }

        // A state file that cannot be read is treated as a run in progress.
        if (!is_array($data) || !is_string($data['status'] ?? null)) {
            return true;
        }

        return in_array($data['status'], [RunStatus::Requested->value, RunStatus::Running->value, RunStatus::Failed->value], true);
    }
}

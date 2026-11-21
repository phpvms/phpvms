<?php

declare(strict_types=1);

namespace App\Features\Setup\Contracts;

use App\Features\Setup\Events\CollectingPostUpgradeTasks;
use Closure;

/**
 * Data work that runs as the `data_tasks` step of the update run.
 * Register one by listening for {@see CollectingPostUpgradeTasks}.
 */
interface PostUpgradeTask
{
    /**
     * Stable id; the state lives in kvp under `post_upgrade.<key>`.
     */
    public function key(): string;

    public function label(): string;

    /**
     * Cheap: once the task is done, read saved state only, never scan big tables.
     */
    public function pending(): bool;

    /**
     * Resume from saved progress and run to completion.
     *
     * @param (Closure(int $done, int $total): void)|null $progress
     */
    public function run(?Closure $progress = null): void;

    /**
     * @return array{done: int, total: int}
     */
    public function progress(): array;
}

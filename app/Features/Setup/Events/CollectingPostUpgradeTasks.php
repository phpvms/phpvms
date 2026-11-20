<?php

declare(strict_types=1);

namespace App\Features\Setup\Events;

use App\Features\Setup\Contracts\PostUpgradeTask;

/**
 * Dispatched by the post-upgrade runner to find its tasks; they run in the order added.
 * Listeners must only call add(): a throwing listener stops the rest from registering.
 */
final class CollectingPostUpgradeTasks
{
    /** @var list<PostUpgradeTask> */
    private array $tasks = [];

    public function add(PostUpgradeTask $task): void
    {
        $this->tasks[] = $task;
    }

    /**
     * @return list<PostUpgradeTask>
     */
    public function tasks(): array
    {
        return $this->tasks;
    }
}

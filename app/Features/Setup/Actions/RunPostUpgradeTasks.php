<?php

declare(strict_types=1);

namespace App\Features\Setup\Actions;

use App\Features\Setup\Contracts\PostUpgradeTask;
use App\Features\Setup\Contracts\SkipsFailingItems;
use App\Features\Setup\Enums\PostUpgradeOutcome;
use App\Features\Setup\Enums\PostUpgradeStatus;
use App\Features\Setup\Events\CollectingPostUpgradeTasks;
use App\Features\Setup\Support\PostUpgradeTaskState;
use App\Features\Setup\Support\SetupLock;
use Closure;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Runs the pending post-upgrade tasks to completion, in the order they were added, one process at a time.
 * A task failure is rethrown; the saved cursor and failure count let the next call resume.
 */
final readonly class RunPostUpgradeTasks
{
    // Consecutive failures at one position before the task may skip the failing items.
    public const int SKIP_AFTER = 3;

    public function __construct(
        private PostUpgradeTaskState $state,
        private SetupLock $lock,
        private Dispatcher $events,
    ) {}

    public static function lockPath(): string
    {
        return storage_path('framework/phpvms-post-upgrade.lock');
    }

    /**
     * @return list<PostUpgradeTask>
     */
    public function tasks(): array
    {
        $event = new CollectingPostUpgradeTasks();
        $this->events->dispatch($event);

        $tasks = [];

        foreach ($event->tasks() as $task) {
            $tasks[$task->key()] ??= $task;
        }

        return array_values($tasks);
    }

    /**
     * Run pending tasks to completion.
     *
     * @param (Closure(PostUpgradeTask $task, int $done, int $total): void)|null $progress
     * @param (Closure(PostUpgradeTask $task): void)|null                        $finished called when a task finishes in this call
     * @param ?string                                                            $only     run just the task with this key
     *
     * @throws Throwable from the failing task
     */
    public function handle(?Closure $progress = null, ?Closure $finished = null, ?string $only = null): PostUpgradeOutcome
    {
        if (!$this->lock->acquire(0)) {
            return PostUpgradeOutcome::Busy;
        }

        try {
            foreach ($this->tasks() as $task) {
                if ($only !== null && $task->key() !== $only) {
                    continue;
                }

                if ($this->runTask($task, $progress) && $finished instanceof Closure) {
                    $finished($task);
                }
            }

            return PostUpgradeOutcome::Done;
        } finally {
            $this->lock->release();
        }
    }

    /**
     * Whether a task has work. Saves `done` for a task that has none, so the next check is cheap.
     */
    public function hasPending(): bool
    {
        return array_any($this->tasks(), fn (PostUpgradeTask $task): bool => $this->isPending($task));
    }

    public function status(string $key): ?PostUpgradeStatus
    {
        return $this->state->status($key);
    }

    private function isPending(PostUpgradeTask $task): bool
    {
        if ($this->state->status($task->key()) === PostUpgradeStatus::Done) {
            return false;
        }

        if ($task->pending()) {
            return true;
        }

        $this->markDone($task);

        return false;
    }

    /**
     * Returns whether the task ran in this call.
     *
     * @param (Closure(PostUpgradeTask $task, int $done, int $total): void)|null $progress
     */
    private function runTask(PostUpgradeTask $task, ?Closure $progress): bool
    {
        if (!$this->isPending($task)) {
            return false;
        }

        $this->state->change($task->key(), static function (array $state): array {
            $state['status'] = PostUpgradeStatus::Running->value;
            $state['started_at'] ??= now()->toIso8601String();

            return $state;
        });

        $tick = $progress instanceof Closure ? static fn (int $done, int $total) => $progress($task, $done, $total) : null;

        if ($tick !== null) {
            $tick(...array_values($task->progress()));
        }

        while (true) {
            try {
                $task->run($tick);
            } catch (Throwable $throwable) {
                if ($this->recordFailure($task, $throwable) >= self::SKIP_AFTER && $task instanceof SkipsFailingItems && $this->skip($task)) {
                    continue;
                }

                throw $throwable;
            }

            break;
        }

        $this->markDone($task);

        return true;
    }

    /**
     * Count a failure; returns the consecutive count at this position.
     */
    private function recordFailure(PostUpgradeTask $task, Throwable $throwable): int
    {
        Log::error('Post-upgrade task '.$task->key().' failed: '.$throwable::class.': '.$throwable->getMessage(), ['exception' => $throwable]);

        try {
            $position = $task->progress()['done'];
        } catch (Throwable) {
            $position = null;
        }

        $failures = 0;

        $this->state->change($task->key(), static function (array $state) use ($position, &$failures): array {
            $failures = $state['failing_at'] === $position && $position !== null ? $state['failures'] + 1 : 1;

            $state['failures'] = $failures;
            $state['failing_at'] = $position;

            return $state;
        });

        return $failures;
    }

    private function skip(SkipsFailingItems&PostUpgradeTask $task): bool
    {
        try {
            $task->skipFailing();
        } catch (Throwable $throwable) {
            Log::error('Post-upgrade task '.$task->key().' could not skip the failing items: '.$throwable->getMessage(), ['exception' => $throwable]);

            return false;
        }

        $this->clearFailure($task->key());

        return true;
    }

    private function clearFailure(string $key): void
    {
        $this->state->change($key, static function (array $state): array {
            $state['failures'] = 0;
            $state['failing_at'] = null;

            return $state;
        });
    }

    private function markDone(PostUpgradeTask $task): void
    {
        $this->state->change($task->key(), static function (array $state): array {
            $state['status'] = PostUpgradeStatus::Done->value;
            $state['failures'] = 0;
            $state['failing_at'] = null;
            $state['finished_at'] = now()->toIso8601String();

            return $state;
        });
    }
}

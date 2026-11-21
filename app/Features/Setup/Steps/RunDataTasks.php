<?php

declare(strict_types=1);

namespace App\Features\Setup\Steps;

use App\Features\Setup\Actions\RunPostUpgradeTasks;
use App\Features\Setup\Contracts\PostUpgradeTask;
use App\Features\Setup\Contracts\Step;
use App\Features\Setup\Enums\PostUpgradeOutcome;
use App\Features\Setup\Enums\SetupStep;
use App\Features\Setup\Support\StepContext;
use RuntimeException;

/**
 * Runs the pending post-upgrade tasks to completion. A Retry resumes each from its saved cursor.
 */
final readonly class RunDataTasks implements Step
{
    public function __construct(private RunPostUpgradeTasks $tasks) {}

    public function step(): SetupStep
    {
        return SetupStep::DataTasks;
    }

    public function applies(StepContext $ctx): bool
    {
        return $this->tasks->hasPending();
    }

    public function run(StepContext $ctx): void
    {
        $outcome = $this->tasks->handle(
            progress: static fn (PostUpgradeTask $task, int $done, int $total) => ($ctx->progress)($done, $total, $task->label()),
            finished: static function (PostUpgradeTask $task) use ($ctx): void {
                $progress = $task->progress();
                ($ctx->log)($task->label().': '.number_format($progress['done']).' / '.number_format($progress['total']));
            },
        );

        if ($outcome === PostUpgradeOutcome::Busy) {
            throw new RuntimeException('Another process is running the post-upgrade tasks. Retry once it finishes.');
        }
    }
}

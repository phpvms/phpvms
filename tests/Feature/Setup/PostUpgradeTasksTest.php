<?php

declare(strict_types=1);

use App\Features\Setup\Actions\RunPostUpgradeTasks;
use App\Features\Setup\Actions\RunSetup;
use App\Features\Setup\Contracts\PostUpgradeTask;
use App\Features\Setup\Contracts\SkipsFailingItems;
use App\Features\Setup\Enums\PostUpgradeStatus;
use App\Features\Setup\Enums\RunOutcome;
use App\Features\Setup\Enums\RunStatus;
use App\Features\Setup\Enums\SetupStep;
use App\Features\Setup\Enums\StepStatus;
use App\Features\Setup\Events\CollectingPostUpgradeTasks;
use App\Features\Setup\Steps\RunDataTasks;
use App\Features\Setup\Support\PostUpgradeTaskState;
use App\Features\Setup\Support\SetupLock;
use App\Features\Setup\Support\SetupRunState;
use App\Features\Setup\Support\UpgradeCompleted;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\ParallelTesting;

/**
 * A task of $units units; `fail['run']` makes the current unit throw.
 */
class FakePostUpgradeTask implements PostUpgradeTask
{
    /**
     * @param ArrayObject<int, mixed>   $log
     * @param ArrayObject<string, bool> $fail
     */
    public function __construct(public string $name, public ArrayObject $log, public int $units, public ArrayObject $fail) {}

    public function key(): string
    {
        return $this->name;
    }

    public function label(): string
    {
        return 'Fake '.$this->name;
    }

    public function pending(): bool
    {
        return $this->done() < $this->units;
    }

    public function run(?Closure $progress = null): void
    {
        $this->log->append([$this->name, 'run']);

        if ($this->fail['run']) {
            throw new RuntimeException('unit '.$this->done().' broke');
        }

        while ($this->pending()) {
            $this->advance();

            if ($progress instanceof Closure) {
                $progress($this->done(), $this->units);
            }
        }
    }

    public function progress(): array
    {
        return ['done' => $this->done(), 'total' => $this->units];
    }

    protected function advance(): void
    {
        app(PostUpgradeTaskState::class)->putData($this->name, ['done' => $this->done() + 1]);
    }

    private function done(): int
    {
        return app(PostUpgradeTaskState::class)->data($this->name)['done'] ?? 0;
    }
}

/**
 * Skipping moves past the failing unit; `fail['skip']` makes the skip throw too.
 */
final class SkippableFakePostUpgradeTask extends FakePostUpgradeTask implements SkipsFailingItems
{
    public function skipFailing(): void
    {
        $this->log->append([$this->name, 'skip']);

        if ($this->fail['skip']) {
            throw new RuntimeException('skip broke too');
        }

        $this->fail['run'] = false;
        $this->advance();
    }
}

/**
 * @param ArrayObject<int, mixed>        $log
 * @param ArrayObject<string, bool>|null $fail
 */
function fakePostUpgradeTask(string $key, ArrayObject $log, int $units = 1, ?ArrayObject $fail = null, bool $skippable = false): PostUpgradeTask
{
    $fail ??= new ArrayObject(['run' => false, 'skip' => false]);

    return $skippable
        ? new SkippableFakePostUpgradeTask($key, $log, $units, $fail)
        : new FakePostUpgradeTask($key, $log, $units, $fail);
}

/**
 * @return ArrayObject<int, mixed>
 */
function postUpgradeLog(): ArrayObject
{
    return new ArrayObject();
}

function registerPostUpgradeTasks(PostUpgradeTask ...$tasks): void
{
    Event::listen(CollectingPostUpgradeTasks::class, function (CollectingPostUpgradeTasks $event) use ($tasks): void {
        foreach ($tasks as $task) {
            $event->add($task);
        }
    });
}

/**
 * A setup run with only the data_tasks step bound; the rest are skipped.
 */
function dataTasksRun(): RunSetup
{
    return new RunSetup(app(SetupRunState::class), app(SetupLock::class), [app(RunDataTasks::class)], app(UpgradeCompleted::class));
}

function requestAndRunDataTasks(): RunOutcome
{
    return dataTasksRun()->requestAndRun('cli', false);
}

function dataTasksStatus(): StepStatus
{
    return app(SetupRunState::class)->read()->steps[SetupStep::DataTasks->value]['status'];
}

test('the step runs pending tasks in the order they were added, reports progress, and leaves them done', function (): void {
    $log = postUpgradeLog();
    registerPostUpgradeTasks(fakePostUpgradeTask('second', $log, units: 2), fakePostUpgradeTask('first', $log));

    expect(requestAndRunDataTasks())->toBe(RunOutcome::Ran)
        ->and(array_column($log->getArrayCopy(), 0))->toBe(['second', 'first'])
        ->and(dataTasksStatus())->toBe(StepStatus::Done)
        ->and(app(PostUpgradeTaskState::class)->status('second'))->toBe(PostUpgradeStatus::Done)
        ->and(app(PostUpgradeTaskState::class)->status('first'))->toBe(PostUpgradeStatus::Done)
        ->and(app(SetupRunState::class)->read()->progress)->toBe(['done' => 1, 'total' => 1, 'item' => 'Fake first'])
        ->and(app(SetupRunState::class)->read()->logTail)->toContain('Fake second: 2 / 2', 'Fake first: 1 / 1');
});

test('the step is skipped when no task is pending, and a done task is not asked again', function (): void {
    $log = postUpgradeLog();
    registerPostUpgradeTasks(fakePostUpgradeTask('once', $log));

    requestAndRunDataTasks();
    app(SetupRunState::class)->clear();

    expect(requestAndRunDataTasks())->toBe(RunOutcome::Ran)
        ->and(dataTasksStatus())->toBe(StepStatus::Skipped)
        ->and($log->count())->toBe(1);
});

test('a failing task fails the run, and Retry resumes it from its saved progress', function (): void {
    $log = postUpgradeLog();
    $fail = new ArrayObject(['run' => false, 'skip' => false]);
    $task = new class('resumable', $log, 4, $fail) extends FakePostUpgradeTask
    {
        public function run(?Closure $progress = null): void
        {
            $this->log->append([$this->name, $this->progress()['done']]);

            while ($this->pending()) {
                if ($this->fail['run'] && $this->progress()['done'] === 2) {
                    throw new RuntimeException('unit 2 broke');
                }

                $this->advance();
            }
        }
    };
    registerPostUpgradeTasks($task);
    $fail['run'] = true;

    expect(requestAndRunDataTasks())->toBe(RunOutcome::Failed)
        ->and(app(SetupRunState::class)->read()->status)->toBe(RunStatus::Failed)
        ->and(app(SetupRunState::class)->read()->error)->toBe('unit 2 broke')
        ->and(dataTasksStatus())->toBe(StepStatus::Failed);

    $fail['run'] = false;

    expect(requestAndRunDataTasks())->toBe(RunOutcome::Ran)
        ->and($log->getArrayCopy())->toBe([['resumable', 0], ['resumable', 2]])
        ->and($task->progress())->toBe(['done' => 4, 'total' => 4]);
});

test('the third failure at one position skips the failing items, across Retries', function (): void {
    $log = postUpgradeLog();
    $fail = new ArrayObject(['run' => true, 'skip' => false]);
    registerPostUpgradeTasks(fakePostUpgradeTask('flaky', $log, units: 3, fail: $fail, skippable: true));

    expect(requestAndRunDataTasks())->toBe(RunOutcome::Failed)
        ->and(requestAndRunDataTasks())->toBe(RunOutcome::Failed)
        ->and(app(PostUpgradeTaskState::class)->read('flaky')['failures'])->toBe(2)
        ->and(in_array(['flaky', 'skip'], $log->getArrayCopy(), true))->toBeFalse();

    expect(requestAndRunDataTasks())->toBe(RunOutcome::Ran)
        ->and(in_array(['flaky', 'skip'], $log->getArrayCopy(), true))->toBeTrue()
        ->and(app(PostUpgradeTaskState::class)->status('flaky'))->toBe(PostUpgradeStatus::Done)
        ->and(app(PostUpgradeTaskState::class)->read('flaky')['failures'])->toBe(0);
});

test('a skip that throws fails the run again', function (): void {
    $log = postUpgradeLog();
    $fail = new ArrayObject(['run' => true, 'skip' => true]);
    registerPostUpgradeTasks(fakePostUpgradeTask('broken', $log, fail: $fail, skippable: true));

    foreach (range(1, RunPostUpgradeTasks::SKIP_AFTER + 1) as $ignored) {
        expect(requestAndRunDataTasks())->toBe(RunOutcome::Failed);
    }

    expect(app(PostUpgradeTaskState::class)->status('broken'))->not->toBe(PostUpgradeStatus::Done);
});

test('the step fails without running anything while another process holds the task lock', function (): void {
    $log = postUpgradeLog();
    registerPostUpgradeTasks(fakePostUpgradeTask('locked', $log));
    $other = new SetupLock(storage_path('framework/testing/phpvms-post-upgrade-'.(ParallelTesting::token() ?: '0').'.lock'));
    $other->acquire();

    try {
        expect(requestAndRunDataTasks())->toBe(RunOutcome::Failed)
            ->and(dataTasksStatus())->toBe(StepStatus::Failed)
            ->and($log->count())->toBe(0);
    } finally {
        $other->release();
    }
});

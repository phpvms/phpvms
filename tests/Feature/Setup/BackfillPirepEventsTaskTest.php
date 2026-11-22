<?php

declare(strict_types=1);

use App\Enums\AcarsType;
use App\Features\Setup\Actions\RunPostUpgradeTasks;
use App\Features\Setup\Actions\RunSetup;
use App\Features\Setup\Enums\PostUpgradeStatus;
use App\Features\Setup\Enums\RunOutcome;
use App\Features\Setup\PostUpgrade\BackfillPirepEvents;
use App\Features\Setup\Steps\RunDataTasks;
use App\Features\Setup\Support\PostUpgradeTaskState;
use App\Features\Setup\Support\SetupLock;
use App\Features\Setup\Support\SetupRunState;
use App\Features\Setup\Support\UpgradeCompleted;
use App\Models\Acars;
use App\Models\Pirep;
use App\Models\PirepEvent;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

function backfillLogRows(int $count): Pirep
{
    $pirep = Pirep::factory()->create();
    Acars::factory()->count($count)->create(['pirep_id' => $pirep->id, 'type' => AcarsType::LOG, 'log' => 'Flaps set to 15']);

    return $pirep;
}

/**
 * @return list<string>
 */
function acarsQueriesDuring(Closure $callback): array
{
    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        if (str_contains($query->sql, 'acars')) {
            $queries[] = $query->sql;
        }
    });

    $callback();

    return $queries;
}

test('a chunk that fails fails the run, and Retry resumes from the saved cursor', function (): void {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        $this->markTestSkipped('Uses a sqlite trigger to fail one chunk.');
    }

    app()->bind(BackfillPirepEvents::class, fn (): BackfillPirepEvents => new BackfillPirepEvents(app(PostUpgradeTaskState::class), chunkSize: 2));
    $pirep = Pirep::factory()->create();
    // Fixed ids: chunks follow id order, so the poisoned row must open the second chunk.
    $rows = collect(['Flaps set to 15', 'Gear down', 'poison', 'Gear up', 'Flaps up'])
        ->map(fn (string $log, int $i) => Acars::factory()->create(['id' => 'resume'.$i, 'pirep_id' => $pirep->id, 'type' => AcarsType::LOG, 'log' => $log]));
    DB::statement("CREATE TRIGGER poison BEFORE INSERT ON pirep_events WHEN NEW.log = 'poison' BEGIN SELECT RAISE(ABORT, 'poisoned row'); END");
    $run = new RunSetup(app(SetupRunState::class), app(SetupLock::class), [app(RunDataTasks::class)], app(UpgradeCompleted::class));

    expect($run->requestAndRun('cli', false))->toBe(RunOutcome::Failed)
        ->and(PirepEvent::count())->toBe(2)
        ->and(app(BackfillPirepEvents::class)->progress())->toBe(['done' => 2, 'total' => 5]);

    // An edit to a copied row survives the Retry only if that chunk is not copied again.
    PirepEvent::whereKey($rows->first()->id)->update(['log' => 'edited']);
    DB::statement('DROP TRIGGER poison');

    expect($run->requestAndRun('cli', false))->toBe(RunOutcome::Ran)
        ->and(PirepEvent::where('pirep_id', $pirep->id)->count())->toBe(5)
        ->and(PirepEvent::find($rows->first()->id)->log)->toBe('edited')
        ->and(app(PostUpgradeTaskState::class)->status(BackfillPirepEvents::KEY))->toBe(PostUpgradeStatus::Done);
});

test('a rerun from the start writes no duplicate events', function (): void {
    backfillLogRows(3);
    $task = new BackfillPirepEvents(app(PostUpgradeTaskState::class), chunkSize: 2);

    $task->run();

    app(PostUpgradeTaskState::class)->putData(BackfillPirepEvents::KEY, []);
    $task->run();

    expect(PirepEvent::count())->toBe(3);
});

test('skips a row that keeps failing on the third failure and copies the rest', function (): void {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        $this->markTestSkipped('Uses a sqlite trigger to fail one row.');
    }

    $pirep = Pirep::factory()->create();
    Acars::factory()->create(['pirep_id' => $pirep->id, 'type' => AcarsType::LOG, 'log' => 'Flaps set to 15']);
    $bad = Acars::factory()->create(['pirep_id' => $pirep->id, 'type' => AcarsType::LOG, 'log' => 'poison']);
    Acars::factory()->create(['pirep_id' => $pirep->id, 'type' => AcarsType::LOG, 'log' => 'Gear down']);
    DB::statement("CREATE TRIGGER poison BEFORE INSERT ON pirep_events WHEN NEW.log = 'poison' BEGIN SELECT RAISE(ABORT, 'poisoned row'); END");

    $runner = app(RunPostUpgradeTasks::class);
    expect(fn () => $runner->handle())->toThrow(QueryException::class, 'poisoned row')
        ->and(fn () => $runner->handle())->toThrow(QueryException::class, 'poisoned row')
        ->and(PirepEvent::count())->toBe(0);

    $runner->handle();

    $data = app(BackfillPirepEvents::class)->load();
    expect(PirepEvent::count())->toBe(2)
        ->and(PirepEvent::find($bad->id))->toBeNull()
        ->and($data['skipped'])->toBe([$bad->id])
        ->and($runner->status(BackfillPirepEvents::KEY))->toBe(PostUpgradeStatus::Done);
});

test('a site with no flight logs is marked done after one check', function (): void {
    $queries = acarsQueriesDuring(fn () => $this->artisan('phpvms:backfill-pirep-events')->assertExitCode(0));

    expect($queries)->toHaveCount(1)
        ->and(app(RunPostUpgradeTasks::class)->status(BackfillPirepEvents::KEY))->toBe(PostUpgradeStatus::Done);
});

test('once done, the command exits without touching acars', function (): void {
    backfillLogRows(2);
    $this->artisan('phpvms:backfill-pirep-events')->assertExitCode(0);

    $queries = acarsQueriesDuring(fn () => $this->artisan('phpvms:backfill-pirep-events')
        ->expectsOutputToContain('Flight logs already copied.')
        ->assertExitCode(0));

    expect($queries)->toBe([]);
});

test('--delete keeps the acars LOG rows when the copy does not finish', function (): void {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        $this->markTestSkipped('Uses a sqlite trigger to fail every row.');
    }

    $pirep = backfillLogRows(2);
    DB::statement("CREATE TRIGGER poison BEFORE INSERT ON pirep_events BEGIN SELECT RAISE(ABORT, 'database refused'); END");

    $this->artisan('phpvms:backfill-pirep-events', ['--delete' => true, '--no-interaction' => true])
        ->expectsOutputToContain('database refused')
        ->assertExitCode(1);

    expect(Acars::where('pirep_id', $pirep->id)->where('type', AcarsType::LOG)->count())->toBe(2)
        ->and(app(RunPostUpgradeTasks::class)->status(BackfillPirepEvents::KEY))->not->toBe(PostUpgradeStatus::Done);
});

test('the command resumes a copy that a failed run left part done', function (): void {
    $pirep = backfillLogRows(3);
    $first = Acars::where('pirep_id', $pirep->id)->orderBy('id')->value('id');
    app(PostUpgradeTaskState::class)->change(BackfillPirepEvents::KEY, function (array $state) use ($first): array {
        $state['status'] = PostUpgradeStatus::Running->value;
        $state['failures'] = 2;
        $state['failing_at'] = 1;
        $state['data'] = ['cursor' => (string) $first, 'copied' => 1, 'total' => 3];

        return $state;
    });

    $this->artisan('phpvms:backfill-pirep-events')->assertExitCode(0);

    expect(PirepEvent::count())->toBe(2)
        ->and(PirepEvent::find($first))->toBeNull()
        ->and(app(RunPostUpgradeTasks::class)->status(BackfillPirepEvents::KEY))->toBe(PostUpgradeStatus::Done);
});

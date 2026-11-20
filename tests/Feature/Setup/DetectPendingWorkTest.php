<?php

declare(strict_types=1);

use App\Features\Setup\Actions\DetectPendingWork;
use App\Features\Setup\Enums\RunStatus;
use App\Features\Setup\Support\MigrationPaths;
use App\Features\Setup\Support\SetupRunState;

beforeEach(function (): void {
    $this->dir = sys_get_temp_dir().'/phpvms-detect-'.uniqid();
    mkdir($this->dir);
    $this->statePath = $this->dir.'/state.json';
    $this->state = new SetupRunState($this->statePath);
    app()->instance(SetupRunState::class, $this->state);
    app()->instance(MigrationPaths::class, new class() extends MigrationPaths
    {
        public function pendingSchema(): array
        {
            return [];
        }

        public function pendingData(): array
        {
            return [];
        }
    });
});

afterEach(function (): void {
    array_map(unlink(...), glob($this->dir.'/*') ?: []);
    @rmdir($this->dir);
});

test('a failed run blocks the panel even with nothing pending', function (): void {
    $this->state->request('tester', false);
    $this->state->markRunning();
    $this->state->finished(RunStatus::Failed);

    $work = app(DetectPendingWork::class)->handle();

    expect($work->pendingMigrations)->toBe([])
        ->and($work->runUnfinished)->toBeTrue()
        ->and($work->blocksPanel())->toBeTrue();
});

test('an enabled addon with unrun seeders does not block the panel', function (): void {
    // Addon seeders are not part of PendingWork at all; only core state counts.
    $work = app(DetectPendingWork::class)->handle();

    expect($work->blocksPanel())->toBeFalse()
        ->and($work->isCurrent())->toBeTrue();
});

test('a corrupt state file blocks the panel without throwing', function (): void {
    file_put_contents($this->statePath, '{not json');

    $work = app(DetectPendingWork::class)->handle();

    expect($work->runUnfinished)->toBeTrue()
        ->and($work->stateError)->not->toBeNull()
        ->and($work->blocksPanel())->toBeTrue();
});

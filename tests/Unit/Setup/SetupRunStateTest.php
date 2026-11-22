<?php

declare(strict_types=1);

use App\Features\Setup\Enums\RunStatus;
use App\Features\Setup\Enums\SetupStep;
use App\Features\Setup\Enums\StepStatus;
use App\Features\Setup\Support\SetupRunState;

beforeEach(function (): void {
    $this->path = sys_get_temp_dir().'/phpvms-run-state-'.uniqid().'.json';
    $this->state = new SetupRunState($this->path);
});

afterEach(function (): void {
    @unlink($this->path);
});

test('request then read returns status requested with all steps pending', function (): void {
    $this->state->request('admin', true);

    $run = $this->state->read();

    expect($run->status)->toBe(RunStatus::Requested)
        ->and($run->requestedBy)->toBe('admin')
        ->and($run->fresh)->toBeTrue()
        ->and($run->steps)->toHaveCount(count(SetupStep::cases()));

    foreach ($run->steps as $step) {
        expect($step['status'])->toBe(StepStatus::Pending);
    }
});

test('stepFailed sets the run error and the step status failed', function (): void {
    $this->state->request('admin', false);
    $this->state->stepFailed(SetupStep::Migrate, 'boom');

    $run = $this->state->read();

    expect($run->error)->toBe('boom')
        ->and($run->steps[SetupStep::Migrate->value]['status'])->toBe(StepStatus::Failed);
});

test('log tail keeps only the last 200 lines', function (): void {
    $this->state->request('admin', false);

    foreach (range(1, 250) as $i) {
        $this->state->appendLog('line '.$i);
    }

    $tail = $this->state->read()->logTail;

    expect($tail)->toHaveCount(200)
        ->and($tail[0])->toBe('line 51');
});

test('read throws RuntimeException on a corrupt file', function (): void {
    file_put_contents($this->path, '{not json');

    $this->state->read();
})->throws(RuntimeException::class, 'unreadable');

test('a run requested again after failed keeps done steps done and keeps fresh', function (): void {
    $this->state->request('admin', true);
    $id = $this->state->read()->id;
    $this->state->stepDone(SetupStep::Preflight);
    $this->state->stepFailed(SetupStep::Migrate, 'boom');
    $this->state->finished(RunStatus::Failed);

    $this->state->request('cron', false);

    $run = $this->state->read();

    expect($run->id)->toBe($id)
        ->and($run->status)->toBe(RunStatus::Requested)
        ->and($run->fresh)->toBeTrue()
        ->and($run->error)->toBeNull()
        ->and($run->steps[SetupStep::Preflight->value]['status'])->toBe(StepStatus::Done)
        ->and($run->steps[SetupStep::Migrate->value]['status'])->toBe(StepStatus::Pending);
});

test('a request after done resets every step', function (): void {
    $this->state->request('admin', true);
    $id = $this->state->read()->id;
    $this->state->stepDone(SetupStep::Preflight);
    $this->state->finished(RunStatus::Done);

    $this->state->request('admin', false);

    $run = $this->state->read();

    expect($run->id)->not->toBe($id)
        ->and($run->status)->toBe(RunStatus::Requested)
        ->and($run->fresh)->toBeFalse()
        ->and($run->steps[SetupStep::Preflight->value]['status'])->toBe(StepStatus::Pending);
});

test('a request while running changes nothing', function (): void {
    $this->state->request('admin', true);
    $this->state->markRunning();
    $this->state->stepDone(SetupStep::Preflight);

    $before = $this->state->read();

    $this->state->request('cron', false);

    expect($this->state->read())->toEqual($before);
});

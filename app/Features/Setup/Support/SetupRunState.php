<?php

declare(strict_types=1);

namespace App\Features\Setup\Support;

use App\Features\Setup\Enums\RunStatus;
use App\Features\Setup\Enums\SetupStep;
use App\Features\Setup\Enums\StepStatus;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * JSON record of the current or last run: step statuses for resume, and the fresh flag for seeding.
 *
 * @phpstan-type StepState array{status: string, startedAt: ?string, finishedAt: ?string}
 * @phpstan-type RunData array{id: string, status: string, requestedBy: string, requestedAt: string, startedAt: ?string, finishedAt: ?string, fresh: bool, steps: array<string, StepState>, progress: ?array{done: int, total: int, item: ?string}, error: ?string, logTail: list<string>}
 */
final readonly class SetupRunState
{
    private const int LOG_TAIL_LINES = 200;

    public function __construct(private string $path) {}

    public static function defaultPath(): string
    {
        return storage_path('framework/phpvms-update.json');
    }

    public function read(): ?RunSnapshot
    {
        $data = $this->load();

        if ($data === null) {
            return null;
        }

        return new RunSnapshot(
            id: $data['id'],
            status: RunStatus::from($data['status']),
            requestedBy: $data['requestedBy'],
            requestedAt: $data['requestedAt'],
            startedAt: $data['startedAt'],
            finishedAt: $data['finishedAt'],
            fresh: $data['fresh'],
            steps: array_map(static fn (array $step): array => [
                'status'     => StepStatus::from($step['status']),
                'startedAt'  => $step['startedAt'],
                'finishedAt' => $step['finishedAt'],
            ], $data['steps']),
            progress: $data['progress'],
            error: $data['error'],
            logTail: $data['logTail'],
        );
    }

    public function request(string $requestedBy, bool $fresh): void
    {
        $data = $this->load();

        if ($data !== null && in_array($data['status'], [RunStatus::Requested->value, RunStatus::Running->value], true)) {
            return;
        }

        if ($data !== null && $data['status'] === RunStatus::Failed->value) {
            foreach ($data['steps'] as $key => $step) {
                if ($step['status'] !== StepStatus::Done->value) {
                    $data['steps'][$key] = $this->pendingStep();
                }
            }

            $data['status'] = RunStatus::Requested->value;
            $data['requestedBy'] = $requestedBy;
            $data['requestedAt'] = now()->toIso8601String();
            $data['finishedAt'] = null;
            $data['progress'] = null;
            $data['error'] = null;

            $this->write($data);

            return;
        }

        $this->write([
            'id'          => (string) Str::ulid(),
            'status'      => RunStatus::Requested->value,
            'requestedBy' => $requestedBy,
            'requestedAt' => now()->toIso8601String(),
            'startedAt'   => null,
            'finishedAt'  => null,
            'fresh'       => $fresh,
            'steps'       => array_fill_keys(array_column(SetupStep::cases(), 'value'), $this->pendingStep()),
            'progress'    => null,
            'error'       => null,
            'logTail'     => [],
        ]);
    }

    public function markRunning(): void
    {
        $this->change(function (array $data): array {
            $data['status'] = RunStatus::Running->value;
            $data['startedAt'] = now()->toIso8601String();

            return $data;
        });
    }

    public function stepStarted(SetupStep $step): void
    {
        $this->changeStep($step, StepStatus::Running, start: true);
    }

    public function stepDone(SetupStep $step): void
    {
        $this->changeStep($step, StepStatus::Done, finish: true);
    }

    public function stepSkipped(SetupStep $step): void
    {
        $this->changeStep($step, StepStatus::Skipped, finish: true);
    }

    public function stepFailed(SetupStep $step, string $error): void
    {
        $this->changeStep($step, StepStatus::Failed, finish: true, error: $error);
    }

    public function progress(int $done, int $total, ?string $item): void
    {
        $this->change(function (array $data) use ($done, $total, $item): array {
            $data['progress'] = ['done' => $done, 'total' => $total, 'item' => $item];

            return $data;
        });
    }

    public function finished(RunStatus $status, ?string $error = null): void
    {
        $this->change(function (array $data) use ($status, $error): array {
            $data['status'] = $status->value;
            $data['finishedAt'] = now()->toIso8601String();

            if ($error !== null) {
                $data['error'] = $error;
            }

            return $data;
        });
    }

    /**
     * Only the tail lives in the state; RunLog keeps the full log.
     */
    public function appendLog(string $line): void
    {
        $this->change(function (array $data) use ($line): array {
            $data['logTail'] = array_slice([...$data['logTail'], $line], -self::LOG_TAIL_LINES);

            return $data;
        });
    }

    /**
     * @throws RuntimeException when the file cannot be removed
     */
    public function clear(): void
    {
        File::delete($this->path);

        if (File::exists($this->path)) {
            throw new RuntimeException('Could not delete the setup run state file '.$this->path.'. Delete it by hand.');
        }
    }

    /**
     * @return StepState
     */
    private function pendingStep(): array
    {
        return ['status' => StepStatus::Pending->value, 'startedAt' => null, 'finishedAt' => null];
    }

    private function changeStep(SetupStep $step, StepStatus $status, bool $start = false, bool $finish = false, ?string $error = null): void
    {
        $this->change(function (array $data) use ($step, $status, $start, $finish, $error): array {
            $entry = $data['steps'][$step->value] ?? $this->pendingStep();
            $entry['status'] = $status->value;

            if ($start) {
                $entry['startedAt'] = now()->toIso8601String();
            }

            if ($finish) {
                $entry['finishedAt'] = now()->toIso8601String();
            }

            $data['steps'][$step->value] = $entry;

            if ($error !== null) {
                $data['error'] = $error;
            }

            return $data;
        });
    }

    /**
     * @param callable(RunData): RunData $change
     */
    private function change(callable $change): void
    {
        $data = $this->load();

        if ($data === null) {
            throw new RuntimeException('The setup run state file '.$this->path.' does not exist. Request a run first.');
        }

        $this->write($change($data));
    }

    /**
     * @return RunData|null
     */
    private function load(): ?array
    {
        if (!File::exists($this->path)) {
            return null;
        }

        try {
            /** @var RunData $data */
            $data = json_decode(File::get($this->path), true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable $throwable) {
            // Guessing "update" here could seed a half-built fresh install wrongly.
            throw new RuntimeException('The setup run state file '.$this->path.' is unreadable. Delete it and run the update again.', 0, $throwable);
        }

        return $data;
    }

    /**
     * @param RunData $state
     */
    private function write(array $state): void
    {
        File::ensureDirectoryExists(dirname($this->path));

        // Written beside the target and renamed, so a crash never leaves a half-written file.
        $temp = $this->path.'.'.getmypid().'.tmp';

        if (file_put_contents($temp, json_encode($state, JSON_THROW_ON_ERROR)) === false) {
            throw new RuntimeException('Could not write the setup run state file '.$temp.'.');
        }

        if (!rename($temp, $this->path)) {
            File::delete($temp);

            throw new RuntimeException('Could not write the setup run state file '.$this->path.'.');
        }
    }
}

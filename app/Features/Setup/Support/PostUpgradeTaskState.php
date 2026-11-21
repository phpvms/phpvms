<?php

declare(strict_types=1);

namespace App\Features\Setup\Support;

use App\Features\Setup\Enums\PostUpgradeStatus;
use App\Models\Kvp;

/**
 * One kvp JSON row per post-upgrade task. In the database, so scheduler and app containers agree.
 * `data` belongs to the task (its cursor and counters); the rest belongs to the runner.
 *
 * @phpstan-type TaskState array{status: string, failures: int, failing_at: ?int, started_at: ?string, updated_at: ?string, finished_at: ?string, data: array<string, mixed>}
 */
final readonly class PostUpgradeTaskState
{
    public const string PREFIX = 'post_upgrade.';

    /**
     * @return TaskState|null
     */
    public function read(string $key): ?array
    {
        $value = Kvp::query()->where('key', self::PREFIX.$key)->value('value');

        if (!is_string($value)) {
            return null;
        }

        $decoded = json_decode($value, true);

        if (!is_array($decoded) || !isset($decoded['status'])) {
            return null;
        }

        /** @var TaskState $state */
        $state = array_merge($this->blank(), $decoded);

        return $state;
    }

    public function status(string $key): ?PostUpgradeStatus
    {
        $state = $this->read($key);

        return $state === null ? null : PostUpgradeStatus::tryFrom($state['status']);
    }

    /**
     * Apply $change to the row, starting from a pending row when there is none.
     *
     * @param callable(TaskState): TaskState $change
     */
    public function change(string $key, callable $change): void
    {
        $state = $change($this->read($key) ?? $this->blank());
        $state['updated_at'] = now()->toIso8601String();

        Kvp::query()->updateOrCreate(
            ['key' => self::PREFIX.$key],
            ['value' => json_encode($state, JSON_THROW_ON_ERROR)],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function data(string $key): array
    {
        return $this->read($key)['data'] ?? [];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function putData(string $key, array $data): void
    {
        $this->change($key, static function (array $state) use ($data): array {
            $state['data'] = $data;

            return $state;
        });
    }

    /**
     * @return TaskState
     */
    private function blank(): array
    {
        return [
            'status'      => PostUpgradeStatus::Pending->value,
            'failures'    => 0,
            'failing_at'  => null,
            'started_at'  => null,
            'updated_at'  => null,
            'finished_at' => null,
            'data'        => [],
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Features\Setup\Support;

use App\Features\Setup\Actions\DetectPendingWork;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * {@see DetectPendingWork} behind a file cache, for per-request callers.
 * The key covers the migration files, the seed marker, the database and the run's id and status.
 * A run clears it when it is requested and when it finishes; the short TTL covers migrations applied outside a run.
 */
final readonly class CachedPendingWork
{
    private const int TTL_SECONDS = 60;

    public function __construct(
        private DetectPendingWork $detect,
        private MigrationPaths $paths,
        private CoreSeeds $seeds,
        private SetupRunState $runState,
        private ?string $path = null,
    ) {}

    public static function defaultPath(): string
    {
        return storage_path('framework/phpvms-pending.json');
    }

    public function handle(): PendingWork
    {
        $fingerprint = $this->fingerprint();
        $cached = $this->read($fingerprint);

        if ($cached instanceof PendingWork) {
            return $cached;
        }

        $work = $this->detect->handle();
        $this->write($fingerprint, $work);

        return $work;
    }

    public function forget(): void
    {
        File::delete($this->path());
    }

    private function fingerprint(): string
    {
        /** @var Migrator $migrator */
        $migrator = app('migrator');
        $connection = DB::connection();

        return hash('sha256', json_encode([
            array_keys($migrator->getMigrationFiles($this->paths->schema())),
            array_keys($migrator->getMigrationFiles($this->paths->data())),
            $this->seeds->pending(),
            [$connection->getName(), $connection->getConfig('host'), $connection->getDatabaseName()],
            $this->runKey(),
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * @return array{string, string}|null
     */
    private function runKey(): ?array
    {
        try {
            $run = $this->runState->read();
        } catch (Throwable) {
            return null;
        }

        return $run instanceof RunSnapshot ? [$run->id, $run->status->value] : null;
    }

    private function read(string $fingerprint): ?PendingWork
    {
        try {
            if (!File::exists($this->path())) {
                return null;
            }

            $data = json_decode(File::get($this->path()), true, flags: JSON_THROW_ON_ERROR);

            if (!is_array($data) || ($data['fingerprint'] ?? null) !== $fingerprint || now()->getTimestamp() - $data['cachedAt'] > self::TTL_SECONDS) {
                return null;
            }

            return new PendingWork(
                freshInstall: (bool) $data['freshInstall'],
                pendingMigrations: array_values($data['pendingMigrations']),
                pendingDataMigrations: array_values($data['pendingDataMigrations']),
                pendingCoreSeeds: (bool) $data['pendingCoreSeeds'],
                runUnfinished: (bool) $data['runUnfinished'],
                stateError: $data['stateError'],
            );
        } catch (Throwable) {
            return null;
        }
    }

    private function write(string $fingerprint, PendingWork $work): void
    {
        try {
            File::ensureDirectoryExists(dirname($this->path()));
            // Renamed into place so a concurrent reader never sees half a file.
            $temp = $this->path().'.'.getmypid().'.tmp';
            File::put($temp, json_encode(['fingerprint' => $fingerprint, 'cachedAt' => now()->getTimestamp(), ...get_object_vars($work)], JSON_THROW_ON_ERROR));
            rename($temp, $this->path());
        } catch (Throwable) {
            // A cache that cannot be written only costs the next request a recompute.
        }
    }

    private function path(): string
    {
        return $this->path ?? self::defaultPath();
    }
}

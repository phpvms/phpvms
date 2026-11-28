<?php

declare(strict_types=1);

namespace App\Features\Setup\Support;

use App\Features\Setup\Actions\DetectPendingWork;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * {@see DetectPendingWork} behind a file cache, for per-request callers.
 * An entry is trusted for the TTL; a run clears it with forget() when it is requested and when it finishes.
 * After the TTL the verdict is recomputed.
 */
final readonly class CachedPendingWork
{
    private const int TTL_SECONDS = 60;

    public function __construct(
        private DetectPendingWork $detect,
        private ?string $path = null,
    ) {}

    public static function defaultPath(): string
    {
        return storage_path('framework/phpvms-pending.json');
    }

    public function handle(): PendingWork
    {
        $cached = $this->read();

        if ($cached instanceof PendingWork) {
            return $cached;
        }

        $work = $this->detect->handle();
        $this->write($work);

        return $work;
    }

    public function forget(): void
    {
        File::delete($this->path());
    }

    private function read(): ?PendingWork
    {
        try {
            if (!File::exists($this->path())) {
                return null;
            }

            $data = json_decode(File::get($this->path()), true, flags: JSON_THROW_ON_ERROR);

            if (!is_array($data) || now()->getTimestamp() - $data['cachedAt'] > self::TTL_SECONDS) {
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

    private function write(PendingWork $work): void
    {
        try {
            File::ensureDirectoryExists(dirname($this->path()));
            // Renamed into place so a concurrent reader never sees half a file.
            $temp = $this->path().'.'.getmypid().'.tmp';
            File::put($temp, json_encode(['cachedAt' => now()->getTimestamp(), ...get_object_vars($work)], JSON_THROW_ON_ERROR));
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

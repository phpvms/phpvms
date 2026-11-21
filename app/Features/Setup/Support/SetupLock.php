<?php

declare(strict_types=1);

namespace App\Features\Setup\Support;

use Illuminate\Support\Facades\File;

/**
 * One update run at a time. A file `flock`, not a cache lock, because the run clears caches;
 * the OS drops it when the process ends.
 */
final class SetupLock
{
    /** @var resource|null */
    private $handle;

    public function __construct(private readonly string $path) {}

    public static function defaultPath(): string
    {
        return storage_path('framework/phpvms-update.lock');
    }

    /**
     * Take the lock, retrying every 200 ms for up to $waitSeconds.
     */
    public function acquire(int $waitSeconds = 0): bool
    {
        if ($this->handle !== null) {
            return true;
        }

        File::ensureDirectoryExists(dirname($this->path));

        $handle = fopen($this->path, 'c');

        if ($handle === false) {
            return false;
        }

        $deadline = microtime(true) + $waitSeconds;

        while (!flock($handle, LOCK_EX | LOCK_NB)) {
            if (microtime(true) >= $deadline) {
                fclose($handle);

                return false;
            }

            usleep(200_000);
        }

        $this->handle = $handle;

        return true;
    }

    public function release(): void
    {
        if ($this->handle === null) {
            return;
        }

        flock($this->handle, LOCK_UN);
        fclose($this->handle);
        $this->handle = null;
    }
}

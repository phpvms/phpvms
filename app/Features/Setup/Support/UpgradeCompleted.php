<?php

declare(strict_types=1);

namespace App\Features\Setup\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Marker file written once a full run of the update procedure has finished.
 */
final readonly class UpgradeCompleted
{
    public function __construct(private string $path, private bool $databaseSignal = true) {}

    public static function defaultPath(): string
    {
        return storage_path('framework/update-7.0-completed');
    }

    public function exists(): bool
    {
        if (is_file($this->path)) {
            return true;
        }

        // A lost file on an 8.x database must not reopen the guest Start path; rewrite it.
        if (!$this->databaseSignal || !$this->hasV8Schema()) {
            return false;
        }

        try {
            $this->write();
        } catch (Throwable) {
            // The database signal still holds when the file cannot be rewritten.
        }

        return true;
    }

    public function write(): void
    {
        File::ensureDirectoryExists(dirname($this->path));
        File::put($this->path, now()->toIso8601String());
    }

    private function hasV8Schema(): bool
    {
        try {
            return Schema::hasTable('migrations')
                && DB::table('migrations')->where('migration', '2026_06_04_000001_create_addons_table')->exists();
        } catch (Throwable) {
            return false;
        }
    }
}

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
    // Also written at the end of the run, so a lost file can be told apart from a run still going.
    public const string KVP_KEY = 'setup.upgrade_completed';

    public function __construct(private string $path, private bool $databaseSignal = true) {}

    public static function defaultPath(): string
    {
        return storage_path('framework/update-7.0-completed');
    }

    /**
     * The marker file alone, without the database fallback.
     */
    public function fileExists(): bool
    {
        return is_file($this->path);
    }

    public function exists(): bool
    {
        if ($this->fileExists()) {
            return true;
        }

        // A lost file on an 8.x database must not reopen the guest Start path; rewrite it.
        if (!$this->databaseSignal || !$this->recordedInDatabase()) {
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
        $at = now()->toIso8601String();

        if ($this->databaseSignal) {
            DB::table('kvp')->updateOrInsert(['key' => self::KVP_KEY], ['value' => $at]);
        }

        File::ensureDirectoryExists(dirname($this->path));
        File::put($this->path, $at);
    }

    private function recordedInDatabase(): bool
    {
        try {
            return Schema::hasTable('kvp') && DB::table('kvp')->where('key', self::KVP_KEY)->exists();
        } catch (Throwable) {
            return false;
        }
    }
}

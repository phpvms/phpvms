<?php

declare(strict_types=1);

namespace App\Features\Setup\Support;

use Illuminate\Support\Facades\File;

/**
 * The full log of one run; the state file only keeps the last lines.
 */
final readonly class RunLog
{
    private function __construct(private string $path) {}

    public static function for(string $runId): self
    {
        return new self(storage_path('logs/setup-'.$runId.'.log'));
    }

    public function write(string $line): void
    {
        File::ensureDirectoryExists(dirname($this->path));
        File::append($this->path, $line.PHP_EOL);
    }
}

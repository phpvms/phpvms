<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\File;

/**
 * The file that records that this site has been installed.
 *
 * The installer writes it once the first admin user exists, and
 * `InstalledCheck` writes it the first time it finds an installed database
 * without one (sites that pre-date the marker). Once it is there, requests no
 * longer query the database to find out whether the site is set up, and the
 * installer stays closed even while the database is down. This is how
 * phpBB, Flarum and WordPress do it too: a file the installer leaves behind,
 * never the database.
 *
 * In the environments below the marker is advisory only: the database is
 * still checked on every request, so a developer who drops the database sees
 * the "not set up" page and can re-run the installer.
 */
final readonly class InstallMarker
{
    /**
     * @var array<int, string>
     */
    private const array VERIFIED_ENVIRONMENTS = ['local', 'demo', 'testing'];

    public function __construct(private string $path) {}

    public static function defaultPath(): string
    {
        return storage_path('framework/installed');
    }

    public function exists(): bool
    {
        return File::exists($this->path);
    }

    /**
     * True when the marker alone proves the site is installed, so nothing
     * needs to ask the database.
     */
    public function isTrusted(): bool
    {
        return !app()->environment(self::VERIFIED_ENVIRONMENTS) && $this->exists();
    }

    public function write(): void
    {
        File::ensureDirectoryExists(dirname($this->path));
        File::put($this->path, now()->toIso8601String().PHP_EOL);
    }

    public function remove(): void
    {
        File::delete($this->path);
    }
}

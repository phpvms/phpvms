<?php

declare(strict_types=1);

namespace App\Features\Addons\Actions;

use App\Features\Addons\AddonAutoLoader;
use App\Features\Addons\Models\AddonManifest;
use App\Features\Addons\Support\AddonSeeders;
use App\Features\Addons\Support\AddonSeedMarkers;
use App\Features\Addons\Support\ManifestParser;
use App\Models\Addon;
use Closure;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Runs one addon's migrations, then its seeders once per version.
 */
final readonly class RunAddonSetup
{
    public function __construct(
        private AddonAutoLoader $autoLoader,
        private ManifestParser $manifestParser,
        private AddonSeedMarkers $markers,
    ) {}

    /**
     * @param (Closure(string): void)|null $log
     *
     * @throws RuntimeException when the migrations fail
     */
    public function handle(Addon $addon, ?Closure $log = null): void
    {
        $path = $addon->getPath();

        if (!is_dir($path)) {
            return;
        }

        // The addon may be missing from the boot cache, so its own classes are
        // not autoloadable yet; its migrations and seeders can reference them.
        $manifest = $this->manifestParser->parse($path);

        if ($manifest instanceof AddonManifest) {
            $this->autoLoader->registerClasses($manifest);
        }

        $this->migrate($addon, $path, $log);
        $this->seed($addon, $path, $log);
    }

    /**
     * @param (Closure(string): void)|null $log
     */
    private function migrate(Addon $addon, string $path, ?Closure $log): void
    {
        $migrations = $path.'/database/migrations';

        if (!is_dir($migrations)) {
            return;
        }

        $exit = Artisan::call('migrate', [
            '--path'     => $migrations,
            '--realpath' => true,
            '--force'    => true,
        ]);

        if ($exit !== 0) {
            throw new RuntimeException(sprintf('Migrations for addon "%s" exited with code %d: %s', $addon->getName(), $exit, trim(Artisan::output())));
        }

        if ($log instanceof Closure) {
            $log(sprintf('Migrated %s', $addon->getName()));
        }
    }

    /**
     * @param (Closure(string): void)|null $log
     */
    private function seed(Addon $addon, string $path, ?Closure $log): void
    {
        $files = AddonSeeders::for($path);

        if ($files === [] || $this->markers->recorded($addon)) {
            return;
        }

        // A faulty seeder must not fail the install or the other addons; no
        // marker is written, so the next run retries it.
        try {
            foreach ($files as $file) {
                $this->runSeederFile($file);
            }
        } catch (Throwable $throwable) {
            // One line: Pail renders only the message, and SQL errors carry newlines.
            Log::error(sprintf(
                'Addon "%s" seeder failed; continuing: %s',
                $addon->getName(),
                preg_replace('/\s+/', ' ', trim($throwable->getMessage())),
            ), ['exception' => $throwable]);

            return;
        }

        $this->markers->record($addon);
        if ($log instanceof Closure) {
            $log(sprintf('Seeded %s', $addon->getName()));
        }
    }

    /**
     * Required by path: addon seeders need not be PSR-4 autoloadable.
     */
    private function runSeederFile(string $file): void
    {
        $class = $this->seederClass($file);

        if ($class === null) {
            return;
        }

        if (!class_exists($class, false)) {
            require_once $file;
        }

        if (!class_exists($class, false)) {
            return;
        }

        app()->call([app($class), 'run']);
    }

    private function seederClass(string $file): ?string
    {
        $contents = file_get_contents($file);

        if ($contents === false) {
            return null;
        }

        $namespace = '';

        if (preg_match('/^namespace\s+([^;]+);/m', $contents, $matches) === 1) {
            $namespace = trim($matches[1]).'\\';
        }

        if (preg_match('/^\s*(?:final\s+|abstract\s+)?class\s+(\w+)/m', $contents, $matches) !== 1) {
            return null;
        }

        return $namespace.$matches[1];
    }
}

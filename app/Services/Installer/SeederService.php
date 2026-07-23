<?php

declare(strict_types=1);

namespace App\Services\Installer;

use App\Addons\AddonRegistry;
use App\Contracts\Service;
use App\Models\Addon;
use App\Models\Kvp;
use Carbon\Carbon;
use Database\Seeders\BaseDataSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Throwable;

class SeederService extends Service
{
    public function __construct(
        private readonly AddonRegistry $addonRegistry,
    ) {}

    /**
     * Synchronize all the seed files, run this after the migrations
     * and on first install.
     */
    public function syncAllSeeds(): void
    {
        app(BaseDataSeeder::class)->run();
        app(SettingsSeeder::class)->run();

        // Persist any new resource/page/custom permissions discovered in the
        // registry (replaces the removed filament-shield generate/seed step).
        Artisan::call('permission:sync');

        $this->seedAddons();
    }

    /**
     * See if there are any seeds that are out of sync (core or addon).
     */
    public function seedsPending(): bool
    {
        if ($this->coreSeedsPending()) {
            return true;
        }

        return $this->addonSeedsPending();
    }

    /**
     * Whether the core (non-addon) seeds are out of sync.
     *
     * Kept separate from addon seeds so the panel's update-pending gate can key
     * on core state alone: a broken addon whose seeder can never succeed must
     * not hold the whole panel in an update-redirect loop.
     */
    public function coreSeedsPending(): bool
    {
        return new SettingsSeeder()->settingsPending();
    }

    /**
     * Run the database seeders for every enabled addon and record a per-addon
     * seed marker so pending detection can clear.
     *
     * Addon seeders are invoked by file path (mirroring how MigrationService
     * loads addon migrations) because addon seeder classes are not guaranteed
     * to be PSR-4 autoloadable by name.
     */
    public function seedAddons(): void
    {
        foreach ($this->addonRegistry->enabled() as $addon) {
            // An addon enabled in the DB but absent on disk (deleted files, a
            // broken symlink) must not be loaded. Skip it rather than resolve a
            // phantom path.
            if (!is_dir($addon->getPath())) {
                Log::warning(sprintf(
                    'Addon "%s" is enabled but its path does not exist on disk; skipping its seeders: %s',
                    $addon->getName(),
                    $addon->getPath(),
                ));

                continue;
            }

            $files = $this->addonSeederFiles($addon);

            if ($files === []) {
                continue;
            }

            // Isolate each addon: a faulty seeder (missing model, bad SQL) must
            // never abort the core install or the seeders of other addons.
            try {
                foreach ($files as $file) {
                    $this->runSeederFile($file);
                }
            } catch (Throwable $throwable) {
                Log::error(sprintf('Addon "%s" seeder failed; continuing', $addon->getName()), [
                    'exception' => $throwable,
                ]);

                continue;
            }

            Kvp::updateOrCreate(
                ['key' => $this->seedMarkerKey($addon)],
                ['value' => Carbon::now('UTC')->toDateTimeString()],
            );
        }
    }

    /**
     * Remove every seed marker for an addon (all versions), so a later reinstall
     * re-runs its seeders. Called when uninstalling an addon with table removal.
     */
    public function clearAddonSeedMarkers(Addon $addon): void
    {
        Kvp::query()
            ->where('key', 'like', 'addon_seeded:'.$addon->getName().':%')
            ->delete();
    }

    /**
     * Whether any enabled addon ships a seeder that has not been recorded as run.
     */
    public function addonSeedsPending(): bool
    {
        foreach ($this->addonRegistry->enabled() as $addon) {
            if ($this->addonSeederFiles($addon) === []) {
                continue;
            }

            if (!Kvp::where('key', $this->seedMarkerKey($addon))->exists()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Locate the `*DatabaseSeeder.php` entry points inside an addon's
     * `database/seeders` directory.
     *
     * @return list<string>
     */
    private function addonSeederFiles(Addon $addon): array
    {
        $path = $addon->getPath();

        if (!is_dir($path)) {
            return [];
        }

        $seedDir = $path.'/database/seeders';

        if (!is_dir($seedDir)) {
            return [];
        }

        return glob($seedDir.'/*DatabaseSeeder.php') ?: [];
    }

    /**
     * Require a seeder file by path and run its declared seeder class.
     *
     * The class is resolved from the file's own namespace/class declaration so
     * an addon shipping a non-PSR-4 namespace still loads.
     */
    private function runSeederFile(string $file): void
    {
        $class = $this->resolveSeederClass($file);

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

    /**
     * Derive the fully-qualified class name declared inside a seeder file.
     */
    private function resolveSeederClass(string $file): ?string
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

    /**
     * KVP marker key for an addon's seed state, versioned so an addon update
     * re-runs its seeders.
     */
    private function seedMarkerKey(Addon $addon): string
    {
        return 'addon_seeded:'.$addon->getName().':'.($addon->version ?? 'base');
    }
}

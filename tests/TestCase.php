<?php

declare(strict_types=1);

namespace Tests;

use App\Addons\Models\AddonBootCache;
use App\Addons\Support\BootCache;
use App\Addons\Support\ManifestParser;
use App\Services\SettingService;
use App\Support\InstallMarker;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\ParallelTesting;
use Override;
use TallStackUi\Components\Icon\Component as IconComponent;

abstract class TestCase extends BaseTestCase
{
    /**
     * Whether this process already checked the boot cache for bundled themes.
     */
    private static bool $bundledThemesPrimed = false;

    /**
     * Boot the application with the bundled theme addons registered.
     *
     * Theme addons register from the boot cache inside `register()`, and the
     * cache is only ever written by a web boot or `addons:cache`. A fresh
     * checkout (CI) has neither, so the bundled theme (airside) would be
     * missing from every test. When the cache lacks a bundled theme, add it
     * and boot again, once per process. Entries already in the cache stay.
     */
    #[Override]
    public function createApplication(): Application
    {
        $app = parent::createApplication();

        if (self::$bundledThemesPrimed) {
            return $app;
        }

        self::$bundledThemesPrimed = true;

        // Under --parallel another worker may write the cache between this
        // worker's first boot and its prime check, so the check alone can
        // report nothing missing while the booted app still has no theme.
        // Decide on the providers the first boot actually registered.
        if ($this->primeBundledThemes($app) || !$this->bundledThemesRegistered($app)) {
            $app = parent::createApplication();
        }

        // TallStackUI's boot read its component config from the first
        // application into a process-wide static, before the theme's provider
        // existed to switch the icon pack. Flushing rebuilds the map at once,
        // so it has to run against the final application's config.
        __ts_get_component_configuration(IconComponent::class, flush: true);

        return $app;
    }

    /**
     * Whether every bundled theme now in the boot cache had its providers
     * registered by the given application.
     */
    private function bundledThemesRegistered(Application $app): bool
    {
        foreach ($app->make(BootCache::class)->read() as $entry) {
            if ($entry->type !== 'theme' || !$entry->enabled) {
                continue;
            }

            foreach ($entry->providers as $provider) {
                if ($app->getProvider($provider) === null) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Add every bundled theme addon missing from the boot cache.
     *
     * @return bool Whether the cache was written
     */
    private function primeBundledThemes(Application $app): bool
    {
        $cache = $app->make(BootCache::class);
        $parser = $app->make(ManifestParser::class);
        $known = array_map(static fn (AddonBootCache $entry): string => $entry->registryId, $cache->read());

        $missing = [];

        foreach (glob(rtrim((string) config('addons.paths.base'), '/').'/*', GLOB_ONLYDIR) ?: [] as $directory) {
            $manifest = $parser->parse(realpath($directory) ?: $directory);

            if ($manifest === null || $manifest->type !== 'theme' || !$manifest->isBundled() || in_array($manifest->registryId, $known, true)) {
                continue;
            }

            $missing[] = new AddonBootCache(
                name: $manifest->name,
                alias: $manifest->alias,
                type: $manifest->type,
                registryId: $manifest->registryId,
                version: $manifest->version,
                namespace: $manifest->namespace,
                providers: $manifest->providers,
                path: $manifest->path,
                autoloadPath: $manifest->autoloadPath,
                layout: $manifest->layout,
                description: $manifest->description,
                enabled: true,
                files: $manifest->files,
            );
        }

        if ($missing === []) {
            return false;
        }

        // No fingerprints: a web boot sees the cache as stale and rebuilds it.
        $cache->write([...$cache->read(), ...$missing]);

        return true;
    }

    /**
     * Seed the settings table for every test.
     *
     * This lives here rather than in a `beforeEach` in tests/Pest.php because
     * that hook is scoped by `->in(...)` to the test directories, and Pest's
     * agent mode (`pest --agent=...`) generates its test file outside them, so
     * the hook never runs for it. Most code paths read settings, so an agent
     * snippet would otherwise execute against an empty settings table.
     *
     * `parent::setUp()` runs first so RefreshDatabase has prepared the schema,
     * which puts the seed at the same point in the lifecycle it occupied as a
     * `beforeEach`.
     */
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        // Keep the install marker out of storage/framework: tests must neither
        // read a marker the host wrote nor leave one behind. One file per
        // parallel worker, or workers race on each other's marker.
        $marker = new InstallMarker(storage_path('framework/testing/installed-'.(ParallelTesting::token() ?: '0')));
        $marker->remove();
        $this->instance(InstallMarker::class, $marker);

        $this->seed(SettingsSeeder::class);

        // The seeder ships `general.auto_airport_lookup` ON, which makes
        // AirportService::lookupAirport() issue a LIVE HTTP request to
        // config('phpvms.api_url') (AirportService.php:86-88). Any test that
        // touches an unknown airport would then pass or fail on network
        // reachability and cache state rather than on the behaviour under test
        // -- and it did: `AirportTest > creates a generic airport` was flaky
        // for exactly this reason.
        //
        // Off by default for every test. A test that wants the lookup path
        // turns it back on itself AND fakes the HTTP response.
        app(SettingService::class)->store('general.auto_airport_lookup', false);

        // The seeder ships `general.theme` as airside (the default for fresh
        // installs). The suite predates that and most theme-dependent tests
        // assume skylight, so every test starts on it. A test that wants
        // airside activates it itself (`useAirside()`), and the fresh-install
        // test resets the row to what the seeder ships.
        app(SettingService::class)->store('general.theme', 'skylight');
    }
}

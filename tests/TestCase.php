<?php

declare(strict_types=1);

namespace Tests;

use App\Features\Addons\Models\AddonBootCache;
use App\Features\Addons\Support\AddonRoots;
use App\Features\Addons\Support\AddonSettingsRegistry;
use App\Features\Addons\Support\BootCache;
use App\Features\Addons\Support\ManifestParser;
use App\Services\SettingService;
use App\Support\InstallMarker;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\ParallelTesting;
use Override;
use ReflectionClass;
use TallStackUi\Components\Icon\Component as IconComponent;

abstract class TestCase extends BaseTestCase
{
    /**
     * The boot cache files this process already primed.
     *
     * @var array<string, true>
     */
    private static array $primed = [];

    /**
     * The test boot cache phpunit.xml configures for core's own tests.
     */
    private static ?string $coreCache = null;

    /**
     * Boot the application from the test boot cache, with the bundled theme
     * addons and the addon under test registered.
     *
     * Addons register from the boot cache inside `register()`. phpunit.xml
     * points it at a test-only file, so the suite never boots the addons
     * enabled on the install it runs in. That file starts empty, so the
     * bundled theme (airside) would be missing from every test. When an
     * addon's own tests run (a test file inside an addon folder), they use a
     * cache file of their own and the addon is enabled in it, so its routes,
     * migrations and declarations exist; core tests never see it. A missing
     * entry is added and the app booted again, once per process and file.
     */
    #[Override]
    public function createApplication(): Application
    {
        $addon = $this->addonUnderTest();

        // phpunit.xml's value, kept so a core test after an addon's test in the
        // same process goes back to core's cache.
        self::$coreCache ??= (string) ($_SERVER['ADDONS_BOOT_CACHE'] ?? 'bootstrap/cache/addons-phpunit.php');
        $_SERVER['ADDONS_BOOT_CACHE'] = $_ENV['ADDONS_BOOT_CACHE'] = $addon === null
            ? self::$coreCache
            : 'bootstrap/cache/addons-phpunit-'.preg_replace('/[^a-z0-9]+/', '-', strtolower(basename($addon))).'.php';

        $app = parent::createApplication();
        $cachePath = $app->make(BootCache::class)->path();

        if (isset(self::$primed[$cachePath])) {
            return $app;
        }

        self::$primed[$cachePath] = true;

        // Under --parallel another worker may write the cache between this
        // worker's first boot and its prime check, so the check alone can
        // report nothing missing while the booted app still lacks an addon.
        // Decide on the providers the first boot actually registered.
        if ($this->primeAddons($app, $addon) || !$this->enabledAddonsRegistered($app)) {
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
     * The folder of the addon whose tests are running: the nearest folder
     * above the test file that holds a manifest. Null for core's own tests.
     */
    private function addonUnderTest(): ?string
    {
        // Pest declares the test file on each generated test class.
        $reflection = new ReflectionClass(static::class);
        $file = $reflection->hasProperty('__filename')
            ? (string) $reflection->getStaticPropertyValue('__filename')
            : (string) $reflection->getFileName();

        $core = realpath(dirname(__DIR__));
        $directory = realpath(dirname($file));

        while ($directory !== false && $directory !== $core && $directory !== dirname($directory)) {
            if (is_file($directory.'/addon.json') || is_file($directory.'/module.json')) {
                return $directory;
            }

            $directory = dirname($directory);
        }

        return null;
    }

    /**
     * Whether every enabled addon now in the boot cache had its providers
     * registered by the given application.
     */
    private function enabledAddonsRegistered(Application $app): bool
    {
        foreach ($app->make(BootCache::class)->read() as $entry) {
            if (!$entry->enabled) {
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
     * Add every bundled theme addon, and the addon under test, that is missing
     * from the boot cache.
     *
     * @return bool Whether the cache was written
     */
    private function primeAddons(Application $app, ?string $addon): bool
    {
        $cache = $app->make(BootCache::class);
        $parser = $app->make(ManifestParser::class);
        $known = array_map(static fn (AddonBootCache $entry): string => $entry->registryId, $cache->read());

        $directories = array_map(static fn (string $name): string => AddonRoots::bundledSource().'/'.$name, AddonRoots::bundled());
        $missing = [];

        foreach ([...$directories, ...($addon !== null ? [$addon] : [])] as $directory) {
            $manifest = $parser->parse(realpath($directory) ?: $directory);

            if ($manifest === null || in_array($manifest->registryId, $known, true)) {
                continue;
            }

            $bundledTheme = $manifest->type === 'theme' && $manifest->isBundled();

            if (!$bundledTheme && $directory !== $addon) {
                continue;
            }

            $known[] = $manifest->registryId;
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
                icon: $manifest->icon,
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
     * Apply the settings every test starts from.
     *
     * Settings need no seeding (each reads as its class default until saved);
     * only the test-wide overrides below are written. This lives here rather
     * than in a `beforeEach` in tests/Pest.php because that hook is scoped by
     * `->in(...)` to the test directories, and Pest's agent mode
     * (`pest --agent=...`) generates its test file outside them, so the hook
     * never runs for it.
     *
     * `parent::setUp()` runs first so RefreshDatabase has prepared the schema.
     */
    #[Override]
    protected function setUp(): void
    {
        // Addon settings registrations are per process; the app booted below
        // registers the enabled addons' classes again.
        AddonSettingsRegistry::flush();

        parent::setUp();

        // Keep the install marker out of storage/framework: tests must neither
        // read a marker the host wrote nor leave one behind. One file per
        // parallel worker, or workers race on each other's marker.
        $marker = new InstallMarker(storage_path('framework/testing/installed-'.(ParallelTesting::token() ?: '0')));
        $marker->remove();
        $this->instance(InstallMarker::class, $marker);

        // The default for `airports.auto_airport_lookup` is ON, which makes
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

        // The default for `general.theme` is airside (the default for fresh
        // installs). The suite predates that and most theme-dependent tests
        // assume skylight, so every test starts on it. A test that wants
        // airside activates it itself (`useAirside()`), and the fresh-install
        // test resets the row to what the seeder ships.
        app(SettingService::class)->store('general.theme', 'skylight');
    }
}

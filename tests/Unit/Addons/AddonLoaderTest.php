<?php

declare(strict_types=1);

use App\Addons\AddonAutoLoader;
use App\Addons\Models\AddonBootCache;
use App\Addons\Support\AutoloadGuard;
use App\Addons\Support\BootCache;
use App\Exceptions\AutoloadModeException;
use Composer\Autoload\ClassLoader;
use Illuminate\Support\Collection;
use Illuminate\Support\ServiceProvider;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Build a minimal boot-cache row array for a given namespace / autoload path.
 *
 * @param  list<string>         $providers
 * @return array<string, mixed>
 */
function addonRow(string $namespace, string $autoloadPath, array $providers = []): array
{
    return [
        'registry_id'   => 'test/sample',
        'type'          => 'module',
        'version'       => '1.0.0',
        'namespace'     => $namespace,
        'path'          => $autoloadPath,
        'enabled'       => true,
        'providers'     => $providers,
        'autoload_path' => $autoloadPath,
        'layout'        => 'app',
        'filament'      => [],
        'name'          => 'Sample',
        'alias'         => 'sample',
        'description'   => '',
    ];
}

/**
 * Build an AddonLoader with a real guard and a runtime stub whose enabled()
 * returns a Collection of AddonBootCache objects built from $rows.
 *
 * @param array<int, array<string, mixed>> $rows
 */
function loaderWithRows(array $rows): AddonAutoLoader
{
    $objects = array_map(AddonBootCache::fromArray(...), $rows);

    $runtime = new class($objects) extends BootCache
    {
        /** @param list<AddonBootCache> $objects */
        public function __construct(private readonly array $objects)
        {
            // Skip parent constructor — no file I/O needed in tests.
        }

        public function enabled(): Collection
        {
            return collect($this->objects);
        }
    };

    return new AddonAutoLoader($runtime, new AutoloadGuard());
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

it('classLoader() returns a ClassLoader instance from the real autoload stack', function (): void {
    $loader = loaderWithRows([])->classLoader();

    expect($loader)->toBeInstanceOf(ClassLoader::class);
});

it('register() adds the PSR-4 prefix to the injected loader for an enabled addon', function (): void {
    // Use a real existing directory so addPsr4 receives a valid path.
    $autoloadPath = app_path('Addons');
    $namespace = 'Modules\\TestSample';

    $injectedLoader = new ClassLoader();
    $addonLoader = loaderWithRows([addonRow($namespace, $autoloadPath)]);

    $addonLoader->register(app(), $injectedLoader);

    $prefixes = $injectedLoader->getPrefixesPsr4();

    expect($prefixes)->toHaveKey('Modules\\TestSample\\')
        ->and($prefixes['Modules\\TestSample\\'])->toContain($autoloadPath);
});

it('register() registers declared service providers on the application', function (): void {
    // Inline test provider — class is defined here so no autoloading needed.
    $providerClass = AddonLoaderTestServiceProvider::class;
    $autoloadPath = app_path('Addons');
    $namespace = 'Modules\\TestProvider';

    $injectedLoader = new ClassLoader();
    $addonLoader = loaderWithRows([addonRow($namespace, $autoloadPath, [$providerClass])]);

    $addonLoader->register(app(), $injectedLoader);

    expect(app()->getLoadedProviders())->toHaveKey($providerClass);
});

it('register() returns early when no enabled addons are present', function (): void {
    $injectedLoader = new ClassLoader();
    $before = $injectedLoader->getPrefixesPsr4();

    $addonLoader = loaderWithRows([]);
    $addonLoader->register(app(), $injectedLoader);

    expect($injectedLoader->getPrefixesPsr4())->toBe($before);
});

it('register() propagates the guard exception and adds no PSR-4 prefix', function (): void {
    $autoloadPath = app_path('Addons');
    $namespace = 'Modules\\Guarded';

    // Stub guard that always throws.
    $throwingGuard = new class() extends AutoloadGuard
    {
        public function assertRuntimeAutoloadSupported(?ClassLoader $loader = null): void
        {
            throw new AutoloadModeException();
        }
    };

    $objects = [AddonBootCache::fromArray(addonRow($namespace, $autoloadPath))];

    $runtime = new class($objects) extends BootCache
    {
        /** @param list<AddonBootCache> $objects */
        public function __construct(private readonly array $objects)
        {
            // Skip parent constructor.
        }

        public function enabled(): Collection
        {
            return collect($this->objects);
        }
    };

    $injectedLoader = new ClassLoader();
    $addonLoader = new AddonAutoLoader($runtime, $throwingGuard);

    expect(fn () => $addonLoader->register(app(), $injectedLoader))
        ->toThrow(AutoloadModeException::class);

    // Guard threw before any addPsr4 — prefix must not be registered.
    expect($injectedLoader->getPrefixesPsr4())->not->toHaveKey('Modules\\Guarded\\');
});

it('register() normalises a namespace already ending with a trailing backslash to exactly one backslash key', function (): void {
    // Namespace supplied WITH trailing backslash — must not produce a double-backslash key.
    $autoloadPath = app_path('Addons');
    $namespace = 'Modules\\AlreadySlashed\\';

    $injectedLoader = new ClassLoader();
    $addonLoader = loaderWithRows([addonRow($namespace, $autoloadPath)]);

    $addonLoader->register(app(), $injectedLoader);

    $prefixes = $injectedLoader->getPrefixesPsr4();

    // Exactly one trailing backslash — the double-backslash variant must not exist.
    expect($prefixes)->toHaveKey('Modules\\AlreadySlashed\\')
        ->and($prefixes)->not->toHaveKey('Modules\\AlreadySlashed\\\\');
});

it('register() registers all providers when a row declares two service providers', function (): void {
    $autoloadPath = app_path('Addons');
    $namespace = 'Modules\\TwoProviders';

    $injectedLoader = new ClassLoader();
    $addonLoader = loaderWithRows([
        addonRow($namespace, $autoloadPath, [
            AddonLoaderTestProviderAlpha::class,
            AddonLoaderTestProviderBeta::class,
        ]),
    ]);

    $addonLoader->register(app(), $injectedLoader);

    $loaded = app()->getLoadedProviders();

    expect($loaded)
        ->toHaveKey(AddonLoaderTestProviderAlpha::class)
        ->and($loaded)->toHaveKey(AddonLoaderTestProviderBeta::class);
});

// ---------------------------------------------------------------------------
// Inline test service provider (defined in file scope to avoid autoloading)
// ---------------------------------------------------------------------------

class AddonLoaderTestServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void {}
}

class AddonLoaderTestProviderAlpha extends ServiceProvider
{
    #[Override]
    public function register(): void {}
}

class AddonLoaderTestProviderBeta extends ServiceProvider
{
    #[Override]
    public function register(): void {}
}

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
 * @param  list<string>         $files
 * @return array<string, mixed>
 */
function addonRow(string $namespace, string $autoloadPath, array $providers = [], array $files = []): array
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
        'name'          => 'Sample',
        'alias'         => 'sample',
        'description'   => '',
        'files'         => $files,
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

it('register() requires declared autoload.files so module helpers become callable', function (): void {
    $autoloadPath = app_path('Addons');
    $namespace = 'Modules\\FilesAddon';

    // A unique global function name so this test is order-independent.
    $fn = 'addon_loader_helper_'.uniqid();
    $helperFile = sys_get_temp_dir().'/addon_loader_helper_'.uniqid().'.php';
    file_put_contents($helperFile, "<?php\nif (!function_exists('{$fn}')) { function {$fn}(): string { return 'ok'; } }\n");

    $injectedLoader = new ClassLoader();
    $addonLoader = loaderWithRows([addonRow($namespace, $autoloadPath, [], [$helperFile])]);

    expect(function_exists($fn))->toBeFalse();

    try {
        $addonLoader->register(app(), $injectedLoader);

        expect(function_exists($fn))->toBeTrue()
            ->and($fn())->toBe('ok');

        // Idempotent: a second register() in the same process must not fatal
        // on a redeclared function.
        $addonLoader->register(app(), $injectedLoader);

        expect(function_exists($fn))->toBeTrue();
    } finally {
        unlink($helperFile);
    }
});

it('register() skips autoload.files paths that do not exist', function (): void {
    $autoloadPath = app_path('Addons');
    $namespace = 'Modules\\MissingFiles';

    $injectedLoader = new ClassLoader();
    $addonLoader = loaderWithRows([
        addonRow($namespace, $autoloadPath, [], [sys_get_temp_dir().'/definitely_missing_'.uniqid().'.php']),
    ]);

    // Must not throw despite the stale path.
    $addonLoader->register(app(), $injectedLoader);

    expect($injectedLoader->getPrefixesPsr4())->toHaveKey('Modules\\MissingFiles\\');
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

// ---------------------------------------------------------------------------
// Loader resolution vs. foreign autoloaders
// ---------------------------------------------------------------------------

/**
 * Phar-distributed tools (PHPStan, PHPUnit) register their own Composer
 * ClassLoader and prepend it, so it sits ahead of ours in the autoload stack.
 * classLoader() must still return *our* loader: the foreign one is
 * classmap-authoritative (which would trip the mode guard) and is thrown away
 * when the tool exits (so addPsr4 on it would silently lose the addon).
 */
it('classLoader() returns our loader, not a foreign one prepended to the stack', function (): void {
    $ours = ClassLoader::getRegisteredLoaders()[base_path('vendor')] ?? null;
    expect($ours)->toBeInstanceOf(ClassLoader::class);

    $foreign = new ClassLoader();
    $foreign->setClassMapAuthoritative(true);
    $foreign->register(true); // prepend, as a phar bootstrap does

    try {
        $resolved = loaderWithRows([])->classLoader();

        expect($resolved)->toBe($ours)
            ->and($resolved->isClassMapAuthoritative())->toBeFalse();
    } finally {
        $foreign->unregister();
    }
});

it('does not report authoritative mode when only a foreign loader is authoritative', function (): void {
    $foreign = new ClassLoader();
    $foreign->setClassMapAuthoritative(true);
    $foreign->register(true);

    try {
        // Would throw AutoloadModeException if the foreign loader were picked.
        loaderWithRows([addonRow('Modules\\ForeignProbe', app_path('Addons'))])
            ->register(app());
    } finally {
        $foreign->unregister();
    }

    expect(true)->toBeTrue();
});

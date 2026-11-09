<?php

declare(strict_types=1);

use App\Addons\AddonAutoLoader;
use App\Addons\Models\AddonBootCache;
use App\Addons\Support\AutoloadGuard;
use App\Addons\Support\BootCache;
use App\Contracts\Addons\AddonServiceProvider;
use App\Features\Themes\ThemeOptions;
use App\Filament\Pages\AddonSettings;
use App\Models\Addon;
use App\Models\AddonSetting;
use App\Models\Role;
use App\Models\User;
use App\Services\AddonSettingSyncService;
use App\Support\Extensions\Extensions;
use Composer\Autoload\ClassLoader;
use Filament\Facades\Filament;
use Filament\Notifications\DatabaseNotification;
use Filament\Panel;
use Filament\PanelRegistry;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Container\Container;
use Illuminate\Foundation\Application;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\View\FileViewFinder;
use Modules\AddonManager\Providers\AddonManagerServiceProvider;
use PhpvmsAddonFixture\Providers\AcmeServiceProvider;
use PhpvmsBadBaseFixture\Providers\LaravelBaseProvider;
use PhpvmsBadBaseFixture\Providers\RemovedBaseProvider;

// ─────────────────────────────────────────────────────────────────────────────
// Probe providers. Each records the calls it receives, and throws from the
// method named in $throwIn. They sit outside a `\Providers\` namespace, so the
// helpers below are overridden to give them an addon identity.
// ─────────────────────────────────────────────────────────────────────────────

trait ProbesAddonCalls
{
    /**
     * @var list<string>
     */
    public static array $calls = [];

    public static ?string $throwIn = null;

    protected function addonBasePath(): string
    {
        return (string) config('probe.base_path');
    }

    protected function addonRootNamespace(): string
    {
        return 'ProbeAddon';
    }

    protected function addonNamespace(): string
    {
        return 'probe';
    }

    private function step(string $method): void
    {
        self::$calls[] = $method;

        if (self::$throwIn === $method) {
            throw new RuntimeException($method.' failed');
        }
    }
}

class ProbeOrderProvider extends AddonServiceProvider
{
    use ProbesAddonCalls;

    protected function onRegister(): void
    {
        $this->step('onRegister');
    }

    protected function onBoot(): void
    {
        $this->step('onBoot');
    }

    public function registerAppComponents(): void
    {
        $this->step('registerAppComponents');
    }

    public function registerPolicies(): void
    {
        $this->step('registerPolicies');
    }

    public function registerEventListeners(): void
    {
        $this->step('registerEventListeners');
    }

    public function registerAppHooks(): void
    {
        $this->step('registerAppHooks');
    }
}

class ProbeLazyProvider extends AddonServiceProvider
{
    use ProbesAddonCalls;

    /**
     * @var list<array<string, mixed>>
     */
    public static array $slots = [];

    public function registerAppSlots(): array
    {
        $this->step('registerAppSlots');

        return self::$slots;
    }

    public function registerSchedule(Schedule $schedule): void
    {
        $schedule->command('probe:declared')->daily();

        $this->step('registerSchedule');
    }
}

class ProbeThemeProvider extends AddonServiceProvider
{
    use ProbesAddonCalls;

    public function configureAppTheme(): void
    {
        $this->step('configureAppTheme');
    }

    /**
     * @var list<string>
     */
    public static array $themes = [];

    public function registerThemeOptions(string $theme): array
    {
        $this->step('registerThemeOptions');
        self::$themes[] = $theme;

        return [Tab::make('probe')];
    }
}

class ProbePanelProvider extends AddonServiceProvider
{
    use ProbesAddonCalls;

    public function registerAdminFilamentPanel(Panel $panel): Panel
    {
        return $panel->brandName('Probe panel');
    }
}

/**
 * No helper overrides: resolves its addon root the way a real provider does.
 */
class ProbeRootProvider extends AddonServiceProvider
{
    public function root(): string
    {
        return $this->addonBasePath();
    }
}

class ProbeSettingsProvider extends AddonServiceProvider
{
    use ProbesAddonCalls;

    public function registerSettings(): array
    {
        return [['key' => 'api_token', 'default' => 'abc', 'name' => 'API token']];
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Write a boot cache with one enabled entry for the probe addon.
 *
 * @param array<string, mixed> $overrides
 */
function probeBootCache(string $provider, array $overrides = []): void
{
    app(BootCache::class)->write([AddonBootCache::fromArray([
        'name'          => 'Probe',
        'alias'         => 'probe',
        'type'          => 'module',
        'registry_id'   => 'acme/probe',
        'version'       => '1.0.0',
        'namespace'     => 'ProbeAddon',
        'providers'     => [$provider],
        'path'          => config('probe.base_path'),
        'autoload_path' => '',
        'layout'        => 'app',
        'enabled'       => true,
        ...$overrides,
    ])]);
}

/**
 * Register a probe provider the way the addon loader does, on the booted app.
 *
 * @param class-string<AddonServiceProvider> $class
 */
function registerProbe(string $class): void
{
    app()->register($class);
}

function superAdmin(): User
{
    $admin = User::factory()->create();
    $admin->assignRole(Role::create(['name' => Role::superAdminName(), 'guard_name' => 'web']));

    return $admin;
}

beforeEach(function (): void {
    $this->probeDir = sys_get_temp_dir().'/asp-probe-'.uniqid('', true);
    File::ensureDirectoryExists($this->probeDir);
    Config::set('probe.base_path', $this->probeDir);
    Config::set('addons.paths.boot_cache', $this->probeDir.'/addons.php');

    foreach ([ProbeOrderProvider::class, ProbeLazyProvider::class, ProbeThemeProvider::class] as $class) {
        $class::$calls = [];
        $class::$throwIn = null;
    }

    ProbeLazyProvider::$slots = [];
    ProbeThemeProvider::$themes = [];
    Addon::query()->delete();
});

afterEach(function (): void {
    File::deleteDirectory($this->probeDir);
});

// ─────────────────────────────────────────────────────────────────────────────
// Call order and auto-wiring
// ─────────────────────────────────────────────────────────────────────────────

it('calls the addon code in the documented order', function (): void {
    probeBootCache(ProbeOrderProvider::class);

    registerProbe(ProbeOrderProvider::class);

    expect(ProbeOrderProvider::$calls)->toBe([
        'onRegister',
        'registerAppComponents',
        'registerPolicies',
        'registerEventListeners',
        'registerAppHooks',
        'onBoot',
    ]);
});

it('has registered the views, translations and migrations before onBoot runs', function (): void {
    $loader = collect(spl_autoload_functions())->first(fn ($entry): bool => is_array($entry) && $entry[0] instanceof ClassLoader)[0];
    $loader->addPsr4('PhpvmsAddonFixture\\', realpath(__DIR__.'/../../fixtures/Addons/acme/app'));

    AcmeServiceProvider::$seenInOnBoot = null;

    app()->register(AcmeServiceProvider::class);

    expect(AcmeServiceProvider::$seenInOnBoot)->toBe(['views' => true, 'translation' => true, 'migrations' => true]);
});

it('lets an addon route override a core route', function (): void {
    Route::get('/asp-overridable', fn (): string => 'core');
    File::ensureDirectoryExists($this->probeDir.'/routes');
    File::put($this->probeDir.'/routes/web.php', '<?php
'.Route::class.'::get(\'/asp-overridable\', fn (): string => \'addon\');
');

    registerProbe(ProbeOrderProvider::class);

    $this->get('/asp-overridable')->assertSeeText('addon');
});

it('takes the addon root from the boot cache entry, whatever the layout', function (): void {
    probeBootCache(ProbeRootProvider::class, ['layout' => 'root']);

    expect(new ProbeRootProvider(app())->root())->toBe($this->probeDir);
});

it('finds the addon root from the provider file when there is no boot cache entry', function (string $autoload, string $providerDirectory, string $namespaceSuffix): void {
    $namespace = 'ProbeRoot'.bin2hex(random_bytes(4));
    $directory = $this->probeDir.'/'.$providerDirectory;

    File::ensureDirectoryExists($directory);
    File::put($this->probeDir.'/module.json', json_encode(['name' => 'Probe', 'registry_id' => 'acme/probe']));
    File::put($this->probeDir.'/composer.json', json_encode(['autoload' => ['psr-4' => [$namespace.'\\' => $autoload]]]));
    File::put($directory.'/Rooted.php', '<?php
namespace '.$namespace.'\\'.$namespaceSuffix.';

class Rooted extends \\'.ProbeRootProvider::class.' {}
');
    require $directory.'/Rooted.php';

    $class = $namespace.'\\'.$namespaceSuffix.'\\Rooted';

    expect(new $class(app())->root())->toBe(realpath($this->probeDir));
})->with([
    'root layout'           => ['.', 'Providers', 'Providers'],
    'app layout'            => ['app/', 'app/Providers', 'Providers'],
    'nested panel provider' => ['app/', 'app/Providers/Filament', 'Providers\\Filament'],
]);

it('wires the resources of an addon once when it lists two providers', function (): void {
    File::ensureDirectoryExists($this->probeDir.'/resources/views');
    probeBootCache(ProbeOrderProvider::class, ['providers' => [ProbeOrderProvider::class, ProbePanelProvider::class]]);

    registerProbe(ProbeOrderProvider::class);
    registerProbe(ProbePanelProvider::class);

    $finder = View::getFinder();

    expect($finder)->toBeInstanceOf(FileViewFinder::class)
        ->and($finder instanceof FileViewFinder ? $finder->getHints()['probe'] : null)->toBe([$this->probeDir.'/resources/views']);
});

// ─────────────────────────────────────────────────────────────────────────────
// Guard and quarantine
// ─────────────────────────────────────────────────────────────────────────────

it('quarantines a third-party addon whose declaration method throws, and keeps booting', function (): void {
    Notification::fake();
    Log::spy();
    probeBootCache(ProbeOrderProvider::class);
    ProbeOrderProvider::$throwIn = 'registerAppComponents';
    $addon = Addon::factory()->create(['name' => 'Probe', 'namespace' => 'ProbeAddon', 'enabled' => true]);
    $admin = superAdmin();

    registerProbe(ProbeOrderProvider::class);

    expect($addon->refresh()->enabled)->toBeFalse();
    Notification::assertSentTo($admin, DatabaseNotification::class, function (DatabaseNotification $notification) use ($admin): bool {
        $data = $notification->toDatabase($admin);

        return str_contains((string) $data['title'], 'Probe') && str_contains((string) $data['body'], 'registerAppComponents');
    });
});

it('quarantines a third-party addon whose onRegister throws, and keeps booting', function (): void {
    Notification::fake();
    probeBootCache(ProbeOrderProvider::class);
    ProbeOrderProvider::$throwIn = 'onRegister';
    $addon = Addon::factory()->create(['name' => 'Probe', 'namespace' => 'ProbeAddon', 'enabled' => true]);
    $admin = superAdmin();

    registerProbe(ProbeOrderProvider::class);

    expect($addon->refresh()->enabled)->toBeFalse();
    Notification::assertSentTo($admin, DatabaseNotification::class);
});

it('only logs when a bundled addon throws, and runs its remaining declarations', function (): void {
    Notification::fake();
    Log::spy();
    probeBootCache(ProbeOrderProvider::class);
    ProbeOrderProvider::$throwIn = 'registerPolicies';
    $addon = Addon::factory()->create(['name' => 'Probe', 'namespace' => 'ProbeAddon', 'enabled' => true, 'bundled' => true]);

    registerProbe(ProbeOrderProvider::class);

    expect($addon->refresh()->enabled)->toBeTrue()
        ->and(ProbeOrderProvider::$calls)->toContain('onBoot');
    Notification::assertNothingSent();
    Log::shouldHaveReceived('error')->withArgs(fn (string $message): bool => str_contains($message, 'registerPolicies'))->atLeast()->once();
});

// ─────────────────────────────────────────────────────────────────────────────
// Lazy declarations
// ─────────────────────────────────────────────────────────────────────────────

it('declares slots only when the slot registry is first used', function (): void {
    // The test application resolves the slot registry while booting, so use a
    // container where it is still unresolved.
    $previous = Container::getInstance();
    $app = new Application($this->probeDir);
    Container::setInstance($previous);
    $app->singleton('extensions', fn (): Extensions => new Extensions());
    ProbeLazyProvider::$slots = [['slot' => 'flight.detail.aside', 'view' => 'probe::aside']];

    $provider = new ProbeLazyProvider($app);
    $provider->register();
    $provider->boot();

    expect($app->resolved('extensions'))->toBeFalse()
        ->and(ProbeLazyProvider::$calls)->not->toContain('registerAppSlots');

    $entries = $app->make('extensions')->slots()->bladeEntriesFor('flight.detail.aside');

    expect(ProbeLazyProvider::$calls)->toContain('registerAppSlots')
        ->and(array_column($entries, 'view'))->toBe(['probe::aside']);
});

it('declares slots at once when the slot registry is already in use', function (): void {
    app('extensions');
    ProbeLazyProvider::$slots = [['slot' => 'flight.detail.aside', 'view' => 'probe::aside']];

    registerProbe(ProbeLazyProvider::class);

    expect(array_column(app('extensions')->slots()->bladeEntriesFor('flight.detail.aside'), 'view'))->toBe(['probe::aside']);
});

it('registers none of the slots and quarantines the addon when one entry is invalid', function (): void {
    Notification::fake();
    probeBootCache(ProbeLazyProvider::class);
    app('extensions');
    ProbeLazyProvider::$slots = [
        ['slot' => 'flight.detail.aside', 'view' => 'probe::aside'],
        ['slot' => 'flight.detail.aside'],
    ];
    $addon = Addon::factory()->create(['name' => 'Probe', 'namespace' => 'ProbeAddon', 'enabled' => true]);
    superAdmin();

    registerProbe(ProbeLazyProvider::class);

    expect(app('extensions')->slots()->bladeEntriesFor('flight.detail.aside'))->toBe([])
        ->and($addon->refresh()->enabled)->toBeFalse();
});

it('removes the events a schedule declaration added before it threw', function (): void {
    probeBootCache(ProbeLazyProvider::class);
    ProbeLazyProvider::$throwIn = 'registerSchedule';
    Addon::factory()->create(['name' => 'Probe', 'namespace' => 'ProbeAddon', 'enabled' => true]);
    $before = count(app(Schedule::class)->events());

    registerProbe(ProbeLazyProvider::class);

    expect(count(app(Schedule::class)->events()))->toBe($before);
});

// ─────────────────────────────────────────────────────────────────────────────
// Theme methods
// ─────────────────────────────────────────────────────────────────────────────

it('calls the theme methods for a theme addon and builds its option tabs for the theme name', function (): void {
    probeBootCache(ProbeThemeProvider::class, ['type' => 'theme']);

    registerProbe(ProbeThemeProvider::class);

    $tabs = app(ThemeOptions::class)->tabsFor('Probe');

    expect(ProbeThemeProvider::$calls)->toBe(['configureAppTheme', 'registerThemeOptions'])
        ->and($tabs)->toHaveCount(1)
        ->and($tabs[0])->toBeInstanceOf(Tab::class)
        ->and(ProbeThemeProvider::$themes)->toBe(['Probe']);
});

it('does not call the theme methods for a module addon, and logs a warning naming it', function (): void {
    Log::spy();
    probeBootCache(ProbeThemeProvider::class, ['type' => 'module']);

    registerProbe(ProbeThemeProvider::class);

    expect(ProbeThemeProvider::$calls)->toBe([])
        ->and(app(ThemeOptions::class)->tabsFor('Probe'))->toBe([]);
    Log::shouldHaveReceived('warning')->withArgs(fn (string $message): bool => str_contains($message, 'Probe') && str_contains($message, 'configureAppTheme'))->once();
});

it('skips the theme methods without throwing when the boot cache has no entry', function (): void {
    registerProbe(ProbeThemeProvider::class);

    expect(ProbeThemeProvider::$calls)->toBe([]);
});

// ─────────────────────────────────────────────────────────────────────────────
// Admin panel and settings
// ─────────────────────────────────────────────────────────────────────────────

it('serves a declared panel under admin/{alias} with the settings page', function (): void {
    probeBootCache(ProbePanelProvider::class);
    app()->forgetInstance(PanelRegistry::class);

    registerProbe(ProbePanelProvider::class);

    $panel = Filament::getPanel('probe');

    expect($panel->getPath())->toBe('admin/probe')
        ->and($panel->getBrandName())->toBe('Probe panel')
        ->and($panel->getPages())->toContain(AddonSettings::class);
});

it('registers no panel when the admin panel method is not overridden', function (): void {
    probeBootCache(ProbeOrderProvider::class);
    app()->forgetInstance(PanelRegistry::class);

    registerProbe(ProbeOrderProvider::class);

    expect(Filament::getPanels())->not->toHaveKey('probe');
});

it('syncs the settings an addon returns from registerSettings', function (): void {
    probeBootCache(ProbeSettingsProvider::class);

    app(AddonSettingSyncService::class)->sync();

    $row = AddonSetting::query()->where('registry_id', 'acme/probe')->where('key', 'api_token')->first();

    expect($row?->value)->toBe('abc')
        ->and($row?->alias)->toBe('probe');
});

// ─────────────────────────────────────────────────────────────────────────────
// Loader: only the addon base is accepted
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Run the loader over one addon whose manifest lists $provider.
 */
function loadAddonWithProvider(string $provider): void
{
    $loader = collect(spl_autoload_functions())->first(fn ($entry): bool => is_array($entry) && $entry[0] instanceof ClassLoader)[0];
    $loader->addPsr4('PhpvmsBadBaseFixture\\', realpath(__DIR__.'/../../fixtures/Addons/badbase/app'));

    $entry = AddonBootCache::fromArray([
        'name'          => 'BadBase',
        'alias'         => 'badbase',
        'type'          => 'module',
        'registry_id'   => 'acme/badbase',
        'namespace'     => 'PhpvmsBadBaseFixture',
        'providers'     => [$provider],
        'path'          => '',
        'autoload_path' => '',
        'enabled'       => true,
    ]);

    $cache = new class([$entry]) extends BootCache
    {
        /** @param list<AddonBootCache> $entries */
        public function __construct(private readonly array $entries) {}

        public function enabled(): Collection
        {
            return collect($this->entries);
        }
    };

    new AddonAutoLoader($cache, app(AutoloadGuard::class))->register(app(), new ClassLoader());
}

it('does not register a provider that is not on the addon base, and quarantines the addon', function (string $provider): void {
    Notification::fake();
    $addon = Addon::factory()->create(['name' => 'BadBase', 'namespace' => 'PhpvmsBadBaseFixture', 'enabled' => true]);
    $admin = superAdmin();

    loadAddonWithProvider($provider);

    expect(app()->getLoadedProviders())->not->toHaveKey($provider)
        ->and($addon->refresh()->enabled)->toBeFalse();
    Notification::assertSentTo($admin, DatabaseNotification::class);
})->with([
    'Laravel base'  => [LaravelBaseProvider::class],
    'removed class' => [RemovedBaseProvider::class], // @phpstan-ignore class.notFound (fixture extends the removed base, excluded from analysis)
]);

it('only logs a bundled addon on the wrong base, and keeps it enabled', function (): void {
    Notification::fake();
    Log::spy();
    $addon = Addon::factory()->create(['name' => 'BadBase', 'namespace' => 'PhpvmsBadBaseFixture', 'enabled' => true, 'bundled' => true]);

    loadAddonWithProvider(LaravelBaseProvider::class);

    expect($addon->refresh()->enabled)->toBeTrue();
    Notification::assertNothingSent();
    Log::shouldHaveReceived('error')->withArgs(fn (string $message): bool => str_contains($message, 'bundled'))->once();
});

// ─────────────────────────────────────────────────────────────────────────────
// Bundled addon on the base
// ─────────────────────────────────────────────────────────────────────────────

it('schedules the addon manager update check on its configured cadence', function (): void {
    Config::set('addon-manager.update_check_cadence', 'hourly');

    registerProbe(AddonManagerServiceProvider::class);

    $events = collect(app(Schedule::class)->events())
        ->filter(fn ($event): bool => str_contains((string) $event->command, 'addons:check-updates'));

    expect($events)->toHaveCount(1)
        ->and($events->first()->expression)->toBe('0 * * * *');
});

<?php

declare(strict_types=1);

use App\Addons\AddonAutoLoader;
use App\Addons\Models\AddonBootCache;
use App\Addons\Support\AutoloadGuard;
use App\Addons\Support\BootCache;
use App\Contracts\Addons\AddonServiceProvider;
use App\Features\AddonSettings\Setting;
use App\Features\Themes\ThemeOptions;
use App\Features\Tiles\Tile;
use App\Features\Tiles\Tiles;
use App\Filament\Pages\AddonSettings;
use App\Models\Addon;
use App\Models\AddonSetting;
use App\Models\Role;
use App\Models\User;
use App\Services\AddonSettingSyncService;
use App\Services\PermissionRegistry;
use App\Support\Metrics\Facades\Metrics;
use App\Support\Metrics\Metric;
use App\Support\Permissions\Scope;
use Composer\Autoload\ClassLoader;
use Filament\Facades\Filament;
use Filament\Notifications\DatabaseNotification;
use Filament\Panel;
use Filament\PanelRegistry;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Modules\AddonManager\Providers\AddonManagerServiceProvider;
use OtherAddon\Models\Foreign;
use PhpvmsAddonFixture\Providers\AcmeServiceProvider;
use PhpvmsBadBaseFixture\Providers\LaravelBaseProvider;
use PhpvmsBadBaseFixture\Providers\RemovedBaseProvider;
use ProbeAddon\Models\ProbeThing;
use ProbeAddon\Models\Unprefixed;

require_once __DIR__.'/../../fixtures/Addons/probe-models.php';

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

    public function registerModels(): array
    {
        $this->step('registerModels');

        return [];
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

    public function registerSchedule(Schedule $schedule): void
    {
        $schedule->command('probe:declared')->daily();

        $this->step('registerSchedule');
    }
}

class ProbeTypedProvider extends AddonServiceProvider
{
    use ProbesAddonCalls;

    /**
     * @var list<mixed>
     */
    public static array $tiles = [];

    /**
     * @var list<mixed>
     */
    public static array $metrics = [];

    /**
     * @var list<mixed>
     */
    public static array $scopes = [];

    public function registerAppTiles(): array
    {
        return self::$tiles;
    }

    public function registerMetrics(): array
    {
        return self::$metrics;
    }

    public function registerScopes(): array
    {
        return self::$scopes;
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

class ProbeModelsProvider extends AddonServiceProvider
{
    use ProbesAddonCalls;

    /**
     * @var list<class-string>
     */
    public static array $models = [];

    public function registerModels(): array
    {
        $this->step('registerModels');

        return self::$models;
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

class ProbeGoodSettingsProvider extends AddonServiceProvider
{
    use ProbesAddonCalls;

    public function registerSettings(): array
    {
        return [Setting::text('fine', 'Fine')];
    }
}

class ProbeSettingsProvider extends AddonServiceProvider
{
    use ProbesAddonCalls;

    /**
     * @var list<mixed>
     */
    public static array $settings = [];

    public function registerSettings(): array
    {
        return self::$settings;
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

    foreach ([ProbeOrderProvider::class, ProbeLazyProvider::class, ProbeThemeProvider::class, ProbeModelsProvider::class, ProbeTypedProvider::class, ProbeSettingsProvider::class] as $class) {
        $class::$calls = [];
        $class::$throwIn = null;
    }

    ProbeTypedProvider::$tiles = [];
    ProbeTypedProvider::$metrics = [];
    ProbeTypedProvider::$scopes = [];
    ProbeSettingsProvider::$settings = [];
    ProbeModelsProvider::$models = [];
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
        'registerModels',
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

function probeTile(string $id): Closure
{
    return fn (): Tile => Tile::livewire(id: $id, component: 'probe::tile', title: $id, icon: 'plane');
}

it('registers the tiles a provider declares', function (): void {
    ProbeTypedProvider::$tiles = [probeTile('probe-a'), probeTile('probe-b')];

    registerProbe(ProbeTypedProvider::class);

    expect(app(Tiles::class)->has('probe-a'))->toBeTrue()
        ->and(app(Tiles::class)->has('probe-b'))->toBeTrue();
});

it('registers none of the tiles when one builder throws', function (): void {
    Notification::fake();
    probeBootCache(ProbeTypedProvider::class);
    ProbeTypedProvider::$tiles = [probeTile('probe-a'), fn (): Tile => throw new RuntimeException('bad tile')];
    $addon = Addon::factory()->create(['name' => 'Probe', 'namespace' => 'ProbeAddon', 'enabled' => true]);
    superAdmin();

    registerProbe(ProbeTypedProvider::class);

    expect(app(Tiles::class)->has('probe-a'))->toBeFalse()
        ->and($addon->refresh()->enabled)->toBeFalse();
});

it('quarantines the addon naming registerAppTiles when an entry is not a closure', function (): void {
    Notification::fake();
    probeBootCache(ProbeTypedProvider::class);
    ProbeTypedProvider::$tiles = [probeTile('probe-a'), 'probe-b'];
    $addon = Addon::factory()->create(['name' => 'Probe', 'namespace' => 'ProbeAddon', 'enabled' => true]);
    $admin = superAdmin();

    registerProbe(ProbeTypedProvider::class);

    expect(app(Tiles::class)->has('probe-a'))->toBeFalse()
        ->and($addon->refresh()->enabled)->toBeFalse();
    Notification::assertSentTo($admin, DatabaseNotification::class, fn (DatabaseNotification $notification): bool => str_contains((string) $notification->toDatabase($admin)['body'], 'registerAppTiles'));
});

it('registers none of the metrics and quarantines the addon when one entry is not a Metric', function (): void {
    Notification::fake();
    probeBootCache(ProbeTypedProvider::class);
    ProbeTypedProvider::$metrics = [
        Metric::integer('probe.one', fn (): int => 1),
        ['id' => 'probe.two', 'resolve' => fn (): int => 2],
        Metric::integer('probe.three', fn (): int => 3),
    ];
    $addon = Addon::factory()->create(['name' => 'Probe', 'namespace' => 'ProbeAddon', 'enabled' => true]);
    superAdmin();

    registerProbe(ProbeTypedProvider::class);

    expect(Metrics::has('probe.one'))->toBeFalse()
        ->and(Metrics::has('probe.three'))->toBeFalse()
        ->and($addon->refresh()->enabled)->toBeFalse();
});

it('registers the metrics a provider declares', function (): void {
    ProbeTypedProvider::$metrics = [Metric::float('probe.hours', fn (): float => 1.5, 'Probe hours', 'probe', 'decimal_1', 60)];

    registerProbe(ProbeTypedProvider::class);

    expect(Metrics::registry()->get('probe.hours'))->toMatchArray(['value_type' => 'float', 'format' => 'decimal_1', 'ttl' => 60])
        ->and(Metrics::resolve('probe.hours'))->toBe(1.5);
});

it('syncs the settings an addon returns from registerSettings', function (): void {
    probeBootCache(ProbeSettingsProvider::class);
    ProbeSettingsProvider::$settings = [Setting::int('retries', 'Retries', 3, 'Network', 7, 'How often to retry')];

    app(AddonSettingSyncService::class)->sync();

    $row = AddonSetting::query()->where('registry_id', 'acme/probe')->where('key', 'retries')->first();

    expect($row?->alias)->toBe('probe')
        ->and($row?->type)->toBe('int')
        ->and($row?->value)->toBe('3')
        ->and($row?->default)->toBe('3')
        ->and($row?->group)->toBe('Network')
        ->and($row?->order)->toBe(7)
        ->and($row?->description)->toBe('How often to retry');
});

it('keeps a saved setting value when the schema syncs again', function (): void {
    probeBootCache(ProbeSettingsProvider::class);
    ProbeSettingsProvider::$settings = [Setting::int('retries', 'Retries', 3)];
    app(AddonSettingSyncService::class)->sync();
    AddonSetting::query()->where('key', 'retries')->update(['value' => '9']);

    app(AddonSettingSyncService::class)->sync();

    expect(AddonSetting::query()->where('key', 'retries')->first()->value)->toBe('9');
});

it('stores a hidden setting but does not list it on the settings page', function (): void {
    probeBootCache(ProbeSettingsProvider::class);
    Addon::factory()->create(['name' => 'Probe', 'namespace' => 'ProbeAddon', 'registry_id' => 'acme/probe', 'enabled' => true]);
    ProbeSettingsProvider::$settings = [Setting::hidden('cursor', 'abc')];
    app(AddonSettingSyncService::class)->sync();
    Filament::setCurrentPanel(Panel::make()->id('acme/probe'));

    expect(AddonSetting::query()->where('key', 'cursor')->first()?->type)->toBe('hidden')
        ->and(AddonSettings::canAccess())->toBeFalse();

    ProbeSettingsProvider::$settings = [Setting::hidden('cursor', 'abc'), Setting::text('label', 'Label')];
    app(AddonSettingSyncService::class)->sync();

    expect(AddonSettings::canAccess())->toBeTrue();
});

it('skips only the addon whose settings list holds a non-Setting entry', function (): void {
    Log::spy();
    probeBootCache(ProbeSettingsProvider::class);
    ProbeSettingsProvider::$settings = [Setting::text('good', 'Good'), ['key' => 'legacy']];

    app(AddonSettingSyncService::class)->sync();

    expect(AddonSetting::query()->where('registry_id', 'acme/probe')->count())->toBe(0);
    Log::shouldHaveReceived('warning')->withArgs(fn (string $message): bool => str_contains($message, 'ProbeSettingsProvider'))->once();
});

it('still syncs the next addon when one addon returns a bad settings list', function (): void {
    app(BootCache::class)->write([
        AddonBootCache::fromArray([
            'name'          => 'Probe', 'alias' => 'probe', 'type' => 'module', 'registry_id' => 'acme/probe', 'version' => '1.0.0',
            'namespace'     => 'ProbeAddon', 'providers' => [ProbeSettingsProvider::class], 'path' => config('probe.base_path'),
            'autoload_path' => '', 'layout' => 'app', 'enabled' => true,
        ]),
        AddonBootCache::fromArray([
            'name'          => 'Probe Two', 'alias' => 'probe-two', 'type' => 'module', 'registry_id' => 'acme/probe-two', 'version' => '1.0.0',
            'namespace'     => 'ProbeAddon', 'providers' => [ProbeGoodSettingsProvider::class], 'path' => config('probe.base_path'),
            'autoload_path' => '', 'layout' => 'app', 'enabled' => true,
        ]),
    ]);
    ProbeSettingsProvider::$settings = [['key' => 'legacy']];

    app(AddonSettingSyncService::class)->sync();

    expect(AddonSetting::query()->where('registry_id', 'acme/probe')->count())->toBe(0)
        ->and(AddonSetting::query()->where('registry_id', 'acme/probe-two')->pluck('key')->all())->toBe(['fine']);
});

it('registers the scopes a provider declares with their group and label', function (): void {
    ProbeTypedProvider::$scopes = [new Scope('x:probe-a', 'ProbeGroup', 'Probe A'), new Scope('x:probe-b')];

    registerProbe(ProbeTypedProvider::class);

    $registry = app(PermissionRegistry::class);
    $permissions = collect($registry->grouped())->flatMap(fn (array $group): array => $group['permissions'])->keyBy('name');

    expect($registry->apiScopes())->toContain('x:probe-a', 'x:probe-b')
        ->and($permissions['x:probe-a']['label'])->toBe('Probe A');
});

it('registers no scope and quarantines the addon when one entry is not a Scope', function (): void {
    Notification::fake();
    probeBootCache(ProbeTypedProvider::class);
    ProbeTypedProvider::$scopes = [new Scope('x:probe-a'), 'x:probe-b'];
    $addon = Addon::factory()->create(['name' => 'Probe', 'namespace' => 'ProbeAddon', 'enabled' => true]);
    superAdmin();

    registerProbe(ProbeTypedProvider::class);

    expect(app(PermissionRegistry::class)->apiScopes())->not->toContain('x:probe-a')
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
// Models
// ─────────────────────────────────────────────────────────────────────────────

it('accepts models inside the addon namespace', function (): void {
    Notification::fake();
    probeBootCache(ProbeModelsProvider::class);
    ProbeModelsProvider::$models = [ProbeThing::class, Unprefixed::class];
    $addon = Addon::factory()->create(['name' => 'Probe', 'namespace' => 'ProbeAddon', 'enabled' => true]);

    registerProbe(ProbeModelsProvider::class);

    expect($addon->refresh()->enabled)->toBeTrue();
    Notification::assertNothingSent();
});

it('quarantines the addon for a model it must not declare', function (string $model, string $named): void {
    Notification::fake();
    probeBootCache(ProbeModelsProvider::class);
    ProbeModelsProvider::$models = [$model];
    $addon = Addon::factory()->create(['name' => 'Probe', 'namespace' => 'ProbeAddon', 'enabled' => true]);
    $admin = superAdmin();

    registerProbe(ProbeModelsProvider::class);

    expect($addon->refresh()->enabled)->toBeFalse();
    Notification::assertSentTo($admin, DatabaseNotification::class, fn (DatabaseNotification $notification): bool => str_contains((string) $notification->toDatabase($admin)['body'], $named));
})->with([
    'a core model'             => [User::class, User::class],
    "another addon's model"    => [Foreign::class, Foreign::class],
    'a class that is no model' => [stdClass::class, stdClass::class],
]);

it('checks the namespace from the provider when the boot cache has no entry', function (): void {
    Notification::fake();
    ProbeModelsProvider::$models = [Unprefixed::class];
    $addon = Addon::factory()->create(['name' => 'Probe', 'namespace' => 'ProbeAddon', 'enabled' => true]);

    registerProbe(ProbeModelsProvider::class);
    expect($addon->refresh()->enabled)->toBeTrue();

    ProbeModelsProvider::$models = [User::class];
    app()->forgetInstance(ProbeModelsProvider::class);
    new ProbeModelsProvider(app())->boot();

    expect($addon->refresh()->enabled)->toBeFalse();
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

<?php

declare(strict_types=1);

namespace App\Contracts\Addons;

use App\Addons\Models\AddonBootCache;
use App\Addons\Support\AddonPanelBuilder;
use App\Addons\Support\AddonQuarantine;
use App\Addons\Support\BootCache;
use App\Addons\Support\ThemeAddonRegistrar;
use App\Features\AddonSettings\Setting;
use App\Features\Hooks\Facades\Hooks;
use App\Features\Hooks\Hook;
use App\Features\Themes\ThemeOptions;
use App\Features\Tiles\Tile;
use App\Features\Tiles\Tiles;
use App\Services\PermissionRegistry;
use App\Support\Metrics\Metric;
use App\Support\Metrics\MetricRegistry;
use App\Support\Metrics\Metrics;
use App\Support\Permissions\Scope;
use Closure;
use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Schemas\Components\Component;
use Illuminate\Console\Command;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;
use Override;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionNamedType;
use Throwable;

/**
 * The base service provider for every addon. Each provider an addon lists in
 * its manifest must extend it, or core does not register it and quarantines the
 * addon.
 *
 * Convention: the extending provider lives at:
 *   {addon-root}/app/Providers/XxxServiceProvider.php
 *
 * PSR-4 maps `Vendor\AddonName\` → `{addon-root}/app/`.
 *
 * `register()` and `boot()` are final, so the auto-wiring below cannot be
 * skipped. An addon puts its own code in `onRegister()` and `onBoot()`, which
 * run after the auto-wiring, and overrides the `register*()` / `configure*()`
 * declaration methods it needs. Each declaration method does nothing by
 * default, and core decides when each one is called (see the table below).
 *
 * Auto-wired on boot (all guarded by existence checks):
 *  - config/*.php     → mergeConfigFrom (in register)
 *  - routes/web.php   → loadRoutesFrom
 *  - routes/api.php   → loadRoutesFrom
 *  - routes/console.php → require (closure routes; console context only)
 *  - resources/views  → loadViewsFrom
 *  - lang/            → loadTranslationsFrom
 *  - database/migrations → loadMigrationsFrom
 *  - app/Console/Commands/*.php → commands() (top-level dir only; no subdirectory scan)
 *  - app/Listeners/*.php        → Event::listen, or the hook dispatcher when the
 *                                 first parameter is a filter or gate hook point
 *                                 (top-level dir only; no subdirectory scan)
 *
 * Call order in `register()`: merge config, the admin panel (when
 * `registerAdminFilamentPanel()` is overridden), `onRegister()`.
 *
 * Call order in `boot()`: the auto-wiring, `registerModels()` and its checks,
 * `registerAppComponents()`, `registerPolicies()`, `registerEventListeners()`,
 * `registerAppHooks()`, the lazy declarations, the theme methods (theme addons
 * only), `onBoot()`. Routes load after every provider has booted so addon
 * routes can override core routes; `onBoot()` therefore cannot call `route()`
 * for the addon's own routes.
 *
 * Lazy declarations (`registerAppTiles`, `registerMetrics`,
 * `registerScopes`, `registerSchedule`) run when core first resolves the
 * matching registry in a process, or at once when it is already resolved.
 * The registry-backed ones return data, which core applies as a whole, so a
 * method that throws leaves none of its entries registered.
 *
 * Every call goes through {@see guard()}: when a method throws, the addon is
 * quarantined (disabled, super-admins notified) and phpvms keeps booting. A
 * bundled addon, or the active theme, is logged only.
 *
 * Octane-safe: the only instance state is the cached boot cache entry and the
 * quarantine flag; both are per-provider and per-process.
 */
abstract class AddonServiceProvider extends ServiceProvider
{
    /**
     * Class name of the tile registry. Held as a string because the registry
     * is not part of every branch of core.
     */
    private const string TILES_REGISTRY = Tiles::class;

    private ?AddonBootCache $bootEntry = null;

    private bool $bootEntryLoaded = false;

    private bool $registering = false;

    /**
     * Set once this addon has been disabled, so its remaining calls are skipped.
     */
    private bool $quarantined = false;

    /**
     * Merge addon config, register the admin panel when one is declared, then
     * call {@see onRegister()}.
     */
    #[Override]
    final public function register(): void
    {
        $this->registering = true;

        try {
            $this->registerConfig();

            if ($this->overrides('registerAdminFilamentPanel')) {
                $this->registerAdminPanel();
            }

            $this->guard('onRegister', function (): void {
                $this->onRegister();
            });
        } finally {
            $this->registering = false;
        }
    }

    /**
     * Auto-wire addon resources, apply the addon's declarations, then call
     * {@see onBoot()}.
     */
    final public function boot(): void
    {
        $this->registerRoutes();
        $this->registerViews();
        $this->registerTranslations();
        $this->registerMigrations();
        $this->registerCommands();
        $this->registerListeners();

        $this->declareModels();

        foreach (['registerAppComponents', 'registerPolicies', 'registerEventListeners', 'registerAppHooks'] as $method) {
            if ($this->overrides($method)) {
                $this->guard($method, function () use ($method): void {
                    $this->{$method}();
                });
            }
        }

        $this->declareLazily();
        $this->declareTheme();

        $this->guard('onBoot', function (): void {
            $this->onBoot();
        });
    }

    /**
     * Deferred providers:
     * https://laravel.com/docs/7.x/providers#deferred-providers
     */
    #[Override]
    public function provides(): array
    {
        return [];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Addon hooks: override what you need. Every method is a no-op by default.
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Runs at the end of `register()`, after config is merged. Bind services
     * here. Do not resolve services that need the database.
     */
    protected function onRegister(): void {}

    /**
     * Runs at the end of `boot()`, after the auto-wiring and every declaration
     * method. Addon routes are not loaded yet, so `route()` cannot see them.
     */
    protected function onBoot(): void {}

    /**
     * Register Blade, Livewire or other view components. Called eagerly at boot.
     * Keep it free of side effects that outlive the process.
     */
    public function registerAppComponents(): void {}

    /**
     * Tiles, as a list of closures that each return a {@see Tile}
     * (`Tile::livewire()`, `Tile::view()` or `Tile::element()`). Called when
     * the tile registry is first used. A non-closure entry, or a closure that
     * throws, registers none of the tiles and quarantines the addon.
     *
     * To fill a theme outlet, listen to a `RenderHook` point in `app/Listeners`.
     *
     * @return list<Closure(): Tile>
     */
    public function registerAppTiles(): array
    {
        return [];
    }

    /**
     * Register filter or gate hook listeners by hand, with `Hooks::listen()`.
     * Called eagerly at boot.
     */
    public function registerAppHooks(): void {}

    /**
     * Theme addons only: configure the theme's component library (for example
     * TallStackUI customisation). Called eagerly at boot. Core does not call it
     * for a module addon, and logs a warning.
     */
    public function configureAppTheme(): void {}

    /**
     * Theme addons only: the option tabs for the Theme admin page. Called each
     * time the options form is built, so return fresh components and hold no
     * request state. Core does not call it for a module addon.
     *
     * @return array<int, Component>
     */
    public function registerThemeOptions(string $theme): array
    {
        return [];
    }

    /**
     * Declare the addon's own Filament panel. Receives a panel that already has
     * the module defaults: id and `admin/{key}` path (see {@see adminPanelKey()}),
     * the console chrome, discovery of `Filament/{Resources,Pages,Widgets}`, and
     * the shared addon settings page. Return it, changed or as it is.
     */
    public function registerAdminFilamentPanel(Panel $panel): Panel
    {
        return $panel;
    }

    /**
     * Register event listeners by hand. Called eagerly at boot. Listeners in
     * `app/Listeners` are auto-discovered without this method.
     */
    public function registerEventListeners(): void {}

    /**
     * Register model policies, with `Gate::policy()`. Called eagerly at boot.
     */
    public function registerPolicies(): void {}

    /**
     * Add scheduled tasks. Called when the scheduler is first resolved. If it
     * throws, the events it added are removed again.
     */
    public function registerSchedule(Schedule $schedule): void {}

    /**
     * Permission-backed API scopes, as a list of {@see Scope}
     * (`new Scope($name, $group, $label)`). Called when the permission
     * registry is first used. A non-Scope entry registers none and
     * quarantines the addon.
     *
     * @return list<Scope>
     */
    public function registerScopes(): array
    {
        return [];
    }

    /**
     * Metrics, as a list of {@see Metric} (`Metric::integer()` or
     * `Metric::float()`). Called when the metrics hub is first used. A
     * non-Metric entry registers none and quarantines the addon.
     *
     * @return list<Metric>
     */
    public function registerMetrics(): array
    {
        return [];
    }

    /**
     * The addon's settings schema, synced into the `addon_settings` table on
     * boot (see App\Services\AddonSettingSyncService): new keys are inserted
     * with their `default` as the initial value, metadata is reconciled, and
     * existing user-edited values are preserved.
     *
     * Read values back with `addon_setting()` / `addon_setting_save()`,
     * addressing the addon by its manifest `alias` or `registry_id`.
     *
     * A list of {@see Setting} (`Setting::text()`, `int()`, `float()`, `bool()`,
     * `date()`, `select()` or `hidden()`). A hidden setting is stored but not
     * listed on the settings page.
     *
     * @return list<Setting>
     */
    public function registerSettings(): array
    {
        return [];
    }

    /**
     * The addon's Eloquent models. Each must extend `Model` and live inside the
     * addon's own namespace. On uninstall
     * with "Also remove its data", core drops these tables. Called once at
     * boot, and once on uninstall. A model constructor must stay pure.
     *
     * @return list<class-string<Model>>
     */
    public function registerModels(): array
    {
        return [];
    }

    /**
     * Called after the addon is installed.
     */
    public function onInstall(): void {}

    /**
     * Called after the addon is updated.
     */
    public function onUpdate(string $from, string $to): void {}

    /**
     * Called before the addon's files are removed. $removeData is true when the
     * operator ticked "Also remove its data". A throw is logged and does not
     * stop the uninstall.
     */
    public function onUninstall(bool $removeData): void {}

    // ─────────────────────────────────────────────────────────────────────────
    // Overridable resolution helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Resolve the addon root directory.
     *
     * Default: 3 levels up from the provider file, which lives at
     * `{root}/app/Providers/XxxServiceProvider.php` (PSR-4 maps
     * `Modules\Name\` → `{root}/app`). Resources (config, routes, views, lang,
     * database/migrations) live at `{root}`.
     *
     * Override for non-standard layouts or tests.
     */
    protected function addonBasePath(): string
    {
        return dirname(new ReflectionClass(static::class)->getFileName(), 3);
    }

    /**
     * Resolve the root PHP namespace of this addon.
     *
     * Default: everything before `\Providers\` in the provider's FQCN.
     * e.g. `Modules\Acme\Providers\AcmeServiceProvider` → `Modules\Acme`.
     *
     * Returns null when the namespace can't be inferred (provider not under a
     * `\Providers\` namespace); callers then skip command/listener discovery
     * rather than crashing boot. Override for non-standard namespace layouts.
     */
    protected function addonRootNamespace(): ?string
    {
        if (!str_contains(static::class, '\\Providers\\')) {
            return null;
        }

        return Str::beforeLast(static::class, '\\Providers\\');
    }

    /**
     * Resolve the short identifier used for view/translation namespaces and
     * config-key prefix derivation.
     *
     * Default: lowercased basename of the addon root directory.
     * e.g. `/path/to/Acme` → `acme`.
     *
     * Override to use a custom namespace key.
     */
    protected function addonNamespace(): string
    {
        return Str::lower(basename($this->addonBasePath()));
    }

    /**
     * The key of the addon's admin panel: its id, its `admin/{key}` path and
     * the `access:{key}` permission that gates it.
     *
     * Default: the manifest `alias`. Override to keep an existing panel id.
     */
    protected function adminPanelKey(): string
    {
        $alias = $this->bootEntry()?->alias;

        return $alias !== null && $alias !== '' ? $alias : $this->addonNamespace();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Guard and quarantine
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Run one call of the addon's code. When it throws, quarantine the addon and
     * return $default, so phpvms keeps booting. Once the addon is disabled,
     * its remaining calls are skipped.
     *
     * A failure in `register()` is held until the application has booted: in
     * the register phase the database and notifications are not ready to use.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn $call
     * @param  TReturn            $default
     * @return TReturn
     */
    private function guard(string $method, Closure $call, mixed $default = null): mixed
    {
        if ($this->quarantined) {
            return $default;
        }

        try {
            return $call();
        } catch (Throwable $throwable) {
            if ($this->registering) {
                $this->app->booted(fn () => $this->quarantine($method, $throwable));
            } else {
                $this->quarantine($method, $throwable);
            }

            return $default;
        }
    }

    private function quarantine(string $method, Throwable $failure): void
    {
        $namespace = $this->bootEntry()->namespace ?? $this->addonRootNamespace() ?? '';

        try {
            $disabled = $this->app->make(AddonQuarantine::class)->handle($namespace, $this->addonNamespace(), $method.'()', $failure);
        } catch (Throwable $throwable) {
            Log::error(sprintf('Addon "%s" quarantine failed for %s(): %s', $this->addonNamespace(), $method, $throwable->getMessage()));

            return;
        }

        $this->quarantined = $this->quarantined || $disabled;
    }

    /**
     * Whether the addon overrides a declaration method, that is, whether the
     * method is declared by a class other than this base.
     */
    private function overrides(string $method): bool
    {
        return new ReflectionMethod($this, $method)->getDeclaringClass()->getName() !== self::class;
    }

    /**
     * The boot cache entry of the addon this provider belongs to, or null when
     * the cache is cold or the provider is not listed in it (unit tests).
     *
     * Read once per provider instance. That is safe here because enabling or
     * disabling an addon reloads Octane workers.
     */
    private function bootEntry(): ?AddonBootCache
    {
        if (!$this->bootEntryLoaded) {
            $this->bootEntryLoaded = true;

            $this->bootEntry = $this->app->make(BootCache::class)
                ->enabled()
                ->first(fn (AddonBootCache $entry): bool => in_array(ltrim(static::class, '\\'), array_map(fn (string $provider): string => ltrim($provider, '\\'), $entry->providers), true));
        }

        return $this->bootEntry;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Declarations
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Check the models the addon declares: each is an Eloquent model, inside the
     * addon's namespace.
     */
    private function declareModels(): void
    {
        if (!$this->overrides('registerModels')) {
            return;
        }

        $this->guard('registerModels', function (): void {
            $entry = $this->bootEntry();
            $namespace = rtrim($entry->namespace ?? $this->addonRootNamespace() ?? '', '\\');

            foreach ($this->registerModels() as $model) {
                if (!class_exists($model) || !is_subclass_of($model, Model::class)) {
                    throw new InvalidArgumentException(sprintf('%s is not an Eloquent model.', $model));
                }

                if ($namespace !== '' && !str_starts_with($model, $namespace.'\\')) {
                    throw new InvalidArgumentException(sprintf('Model %s is outside the addon namespace %s\\.', $model, $namespace));
                }
            }
        });
    }

    /**
     * An addon is third-party code, so a declared list type is checked at run time.
     *
     * @param array<mixed> $items
     * @param class-string $class
     *
     * @throws InvalidArgumentException
     */
    private function requireListOf(array $items, string $class, string $method): void
    {
        foreach ($items as $item) {
            if (!$item instanceof $class) {
                throw new InvalidArgumentException(sprintf('%s() must return a list of %s.', $method, $class));
            }
        }
    }

    /**
     * Wire the declarations that apply when core first uses a registry.
     * Wired only when the method is overridden.
     */
    private function declareLazily(): void
    {
        if ($this->overrides('registerAppTiles')) {
            $this->callAfterResolving(self::TILES_REGISTRY, function (Tiles $tiles): void {
                $this->guard('registerAppTiles', function () use ($tiles): void {
                    $builders = $this->registerAppTiles();

                    $this->requireListOf($builders, Closure::class, 'registerAppTiles');

                    // Build every tile on a scratch registry first, so a throwing builder leaves none registered.
                    $scratch = new Tiles();

                    foreach ($builders as $build) {
                        $scratch->register($build);
                    }

                    foreach ($builders as $build) {
                        $tiles->register($build);
                    }
                });
            });
        }

        if ($this->overrides('registerMetrics')) {
            $this->callAfterResolving('metrics', function (Metrics $metrics): void {
                $this->guard('registerMetrics', function () use ($metrics): void {
                    $list = $this->registerMetrics();

                    $this->requireListOf($list, Metric::class, 'registerMetrics');

                    $scratch = new MetricRegistry();

                    foreach ($list as $metric) {
                        $scratch->register($metric->toDefinition());
                    }

                    foreach ($list as $metric) {
                        $metrics->registry()->register($metric->toDefinition());
                    }
                });
            });
        }

        if ($this->overrides('registerScopes')) {
            $this->callAfterResolving(PermissionRegistry::class, function (PermissionRegistry $registry): void {
                $this->guard('registerScopes', function () use ($registry): void {
                    $scopes = $this->registerScopes();

                    $this->requireListOf($scopes, Scope::class, 'registerScopes');

                    foreach ($scopes as $scope) {
                        $registry->registerApiScope($scope->name, $scope->group, $scope->label);
                    }
                });
            });
        }

        if ($this->overrides('registerSchedule')) {
            $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
                $this->guard('registerSchedule', function () use ($schedule): void {
                    $before = count($schedule->events());

                    try {
                        $this->registerSchedule($schedule);
                    } catch (Throwable $throwable) {
                        // The schedule has no way to remove an event, so cut it back.
                        Closure::bind(function () use ($before): void {
                            $this->events = array_slice($this->events, 0, $before);
                        }, $schedule, Schedule::class)();

                        throw $throwable;
                    }
                });
            });
        }
    }

    /**
     * Theme addons only: configure the theme and register its option tabs.
     * The gate is the manifest type, not the active theme: the active theme is
     * only known after boot, and under Octane a value read at boot goes stale.
     */
    private function declareTheme(): void
    {
        $methods = array_values(array_filter(['configureAppTheme', 'registerThemeOptions'], $this->overrides(...)));

        if ($methods === []) {
            return;
        }

        $entry = $this->bootEntry();

        if (!$entry instanceof AddonBootCache) {
            Log::debug(sprintf('Addon "%s": no boot cache entry, so %s is not called.', $this->addonNamespace(), implode(' and ', $methods)));

            return;
        }

        if ($entry->type !== 'theme') {
            Log::warning(sprintf('Addon "%s" is a %s addon, so %s is not called. Only theme addons use it.', $entry->name, $entry->type, implode(' and ', $methods)));

            return;
        }

        if (in_array('configureAppTheme', $methods, true)) {
            $this->guard('configureAppTheme', function (): void {
                $this->configureAppTheme();
            });
        }

        if (in_array('registerThemeOptions', $methods, true)) {
            $theme = ThemeAddonRegistrar::themeName($entry->path, $entry->name);

            $this->app->make(ThemeOptions::class)->register(
                $theme,
                fn (string $name): array => $this->guard('registerThemeOptions', fn (): array => $this->registerThemeOptions($name), []),
            );
        }
    }

    /**
     * Register the addon's Filament panel from the module panel defaults and
     * {@see registerAdminFilamentPanel()}. Registered with a closure, as
     * `Filament\PanelProvider` does, so the panel is built when Filament boots.
     */
    private function registerAdminPanel(): void
    {
        Filament::registerPanel(function (): Panel {
            $builder = new AddonPanelBuilder(
                $this->app,
                $this->adminPanelKey(),
                $this->panelSourcePath(),
                $this->bootEntry()->namespace ?? $this->addonRootNamespace() ?? '',
            );

            $panel = $builder->panel(Panel::make());

            return $this->guard('registerAdminFilamentPanel', fn (): Panel => $this->registerAdminFilamentPanel($panel), $panel);
        });
    }

    /**
     * The directory the addon's root namespace maps to, where `Filament/` is
     * discovered from.
     */
    private function panelSourcePath(): string
    {
        $path = $this->bootEntry()?->autoloadPath;

        return $path !== null && $path !== '' ? $path : dirname((string) new ReflectionClass(static::class)->getFileName(), 2);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Auto-wiring
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Merge each *.php file in {root}/config/ using its stem as the config key.
     *
     * Important: addons must use unique config filenames (recommended convention:
     * `config/{addonNamespace}.php`) because `mergeConfigFrom` keys by filename
     * stem and two addons sharing a stem will silently no-op the second merge.
     */
    private function registerConfig(): void
    {
        $configDir = $this->addonBasePath().'/config';

        if (!is_dir($configDir)) {
            return;
        }

        foreach (glob($configDir.'/*.php') ?: [] as $file) {
            $this->mergeConfigFrom($file, basename($file, '.php'));
        }
    }

    /**
     * Load web, api, and console route files when present.
     *
     * Routes are loaded from a deferred `booted()` callback so they register
     * after core's routes, allowing addons to override core endpoints. Because
     * this runs after the framework's own name/action lookup refresh, we rebuild
     * those lookups here so addon routes stay resolvable via `route()` and
     * `Route::has()` (mirrors Laravel's RouteServiceProvider).
     */
    private function registerRoutes(): void
    {
        $this->app->booted(function (): void {
            $root = $this->addonBasePath();

            $webRoutes = $root.'/routes/web.php';

            if (file_exists($webRoutes)) {
                $this->loadRoutesFrom($webRoutes);
            }

            $apiRoutes = $root.'/routes/api.php';

            if (file_exists($apiRoutes)) {
                $this->loadRoutesFrom($apiRoutes);
            }

            // Only load console routes in the console context — a bare require on
            // every HTTP worker boot would execute side-effecting closure code.
            if ($this->app->runningInConsole()) {
                $consoleRoutes = $root.'/routes/console.php';

                if (file_exists($consoleRoutes)) {
                    require $consoleRoutes;
                }
            }

            $routes = $this->app['router']->getRoutes();
            $routes->refreshNameLookups();
            $routes->refreshActionLookups();
        });
    }

    /**
     * Register the addon's view namespace when the views directory exists.
     */
    private function registerViews(): void
    {
        $viewsPath = $this->addonBasePath().'/resources/views';

        if (is_dir($viewsPath)) {
            $this->loadViewsFrom($viewsPath, $this->addonNamespace());
        }
    }

    /**
     * Register the addon's translation namespace.
     *
     * Prefers {root}/lang; falls back to {root}/resources/lang.
     */
    private function registerTranslations(): void
    {
        $root = $this->addonBasePath();
        $langPath = is_dir($root.'/lang') ? $root.'/lang' : $root.'/resources/lang';

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, $this->addonNamespace());
        }
    }

    /**
     * Register the addon's database migrations so `artisan migrate` runs them
     * whenever the addon is enabled.
     *
     * Convention: `{root}/database/migrations` (lowercase), mirroring a standard
     * Laravel application and the `modules/*` addon layout.
     */
    private function registerMigrations(): void
    {
        $migrationsPath = $this->addonBasePath().'/database/migrations';

        if (is_dir($migrationsPath)) {
            $this->loadMigrationsFrom($migrationsPath);
        }
    }

    /**
     * Register console commands found in app/Console/Commands/*.php.
     *
     * Only runs in console context. Validates class existence and that it
     * extends Illuminate\Console\Command before registering.
     */
    private function registerCommands(): void
    {
        if (!$this->app->runningInConsole()) {
            return;
        }

        $commandsDir = $this->addonBasePath().'/app/Console/Commands';

        if (!is_dir($commandsDir)) {
            return;
        }

        $rootNamespace = $this->addonRootNamespace();

        if ($rootNamespace === null) {
            return;
        }

        $commands = [];

        foreach (glob($commandsDir.'/*.php') ?: [] as $file) {
            $stem = basename($file, '.php');
            $fqcn = $rootNamespace.'\\Console\\Commands\\'.$stem;

            if (class_exists($fqcn) && is_subclass_of($fqcn, Command::class)) {
                $commands[] = $fqcn;
            }
        }

        if ($commands !== []) {
            $this->commands($commands);
        }
    }

    /**
     * Auto-discover and register event and hook listeners from app/Listeners/*.php.
     *
     * Convention: the listener's `handle` method (or `__invoke` when no
     * `handle` exists) must type-hint a single named event class as its
     * first parameter. Listeners that don't follow this convention are skipped.
     */
    private function registerListeners(): void
    {
        $listenersDir = $this->addonBasePath().'/app/Listeners';

        if (!is_dir($listenersDir)) {
            return;
        }

        $rootNamespace = $this->addonRootNamespace();

        if ($rootNamespace === null) {
            return;
        }

        foreach (glob($listenersDir.'/*.php') ?: [] as $file) {
            $stem = basename($file, '.php');
            $fqcn = $rootNamespace.'\\Listeners\\'.$stem;

            if (!class_exists($fqcn)) {
                continue;
            }

            $eventClass = $this->resolveListenerEventClass($fqcn);

            if ($eventClass === null) {
                continue;
            }

            try {
                $this->registerListener($fqcn, $eventClass);
            } catch (LogicException|InvalidArgumentException $failure) {
                // One broken listener must not stop every boot (the admin panel
                // included): quarantine the addon and skip the rest of its listeners.
                $this->app->make(AddonQuarantine::class)->handle($rootNamespace, $this->addonNamespace(), 'listener '.$fqcn, $failure);

                return;
            }
        }
    }

    /**
     * Bind one listener to its hook point or event.
     *
     * @throws LogicException           when the listener targets a hook point that does not exist
     * @throws InvalidArgumentException when the listener targets a class that is not a hook point
     */
    private function registerListener(string $fqcn, string $eventClass): void
    {
        if (is_subclass_of($eventClass, Hook::class)) {
            Hooks::listen($eventClass, $fqcn, $this->addonNamespace());

            return;
        }

        // A hook point binds by class, so a typo must fail here rather
        // than register an event listener that never fires.
        if (!class_exists($eventClass) && str_starts_with($eventClass, 'App\\Features\\Hooks\\')) {
            throw new LogicException(sprintf(
                'Addon "%s": listener %s targets hook point %s, which does not exist.',
                $this->addonNamespace(),
                $fqcn,
                $eventClass,
            ));
        }

        Event::listen($eventClass, $fqcn);
    }

    /**
     * Reflect a listener class and extract the event class from the first
     * parameter of its `handle` or `__invoke` method.
     *
     * Returns null when no typed single-class parameter is found.
     */
    private function resolveListenerEventClass(string $listenerFqcn): ?string
    {
        try {
            $reflection = new ReflectionClass($listenerFqcn);
        } catch (ReflectionException) {
            return null;
        }

        $method = $reflection->hasMethod('handle')
            ? $reflection->getMethod('handle')
            : ($reflection->hasMethod('__invoke') ? $reflection->getMethod('__invoke') : null);

        if ($method === null || !$method->isPublic()) {
            return null;
        }

        $params = $method->getParameters();

        if ($params === []) {
            return null;
        }

        $type = $params[0]->getType();

        if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
            return null;
        }

        return $type->getName();
    }
}

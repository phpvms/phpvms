<?php

declare(strict_types=1);

namespace App\Contracts\Modules;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Override;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;

/**
 * Batteries-included base provider for new addons.
 *
 * Convention: the extending provider lives at:
 *   {addon-root}/app/Providers/XxxServiceProvider.php
 *
 * PSR-4 maps `Vendor\AddonName\` → `{addon-root}/app/`.
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
 *  - app/Listeners/*.php        → Event::listen (top-level dir only; no subdirectory scan)
 *
 * Octane-safe: no mutable instance state; all registrations are idempotent.
 */
abstract class ServiceProvider extends \Illuminate\Support\ServiceProvider
{
    /**
     * A boot method is required, even if it doesn't do anything.
     * https://laravel.com/docs/7.x/providers#the-boot-method
     *
     * This is normally where you'd register the routes or other startup tasks for your module
     */
    /**
     * Merge addon config files.
     *
     * Runs in register() so config values are available before boot.
     */
    #[Override]
    public function register(): void
    {
        $this->registerConfig();
    }

    /**
     * Auto-wire addon resources.
     *
     * NOTE: subclasses that override boot() must call parent::boot() to keep
     * routes/views/translations/commands/listeners auto-wiring.
     */
    public function boot(): void
    {
        $this->registerRoutes();
        $this->registerViews();
        $this->registerTranslations();
        $this->registerMigrations();
        $this->registerCommands();
        $this->registerListeners();
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

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
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
     * Auto-discover and register event listeners from app/Listeners/*.php.
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

            if ($eventClass !== null) {
                Event::listen($eventClass, $fqcn);
            }
        }
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

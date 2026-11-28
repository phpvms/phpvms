<?php

namespace App\Providers;

use App\Auth\InstallSafeUserProvider;
use App\Console\DataMigrationInstallCommand;
use App\Contracts\Metar;
use App\Contracts\Model as BaseModel;
use App\Enums\ActiveState;
use App\Enums\PirepPhase;
use App\Enums\PirepSource;
use App\Enums\PirepState;
use App\Enums\UserState;
use App\Features\Assets\AssetService;
use App\Features\Assets\AssetTypes;
use App\Features\Hooks\Facades\Hooks;
use App\Features\Hooks\RenderHook;
use App\Features\OAuth\Helpers\OAuthConnectionService;
use App\Features\OAuth\Helpers\SocialiteProviderRegistry;
use App\Features\Setup\Actions\RunPostUpgradeTasks;
use App\Features\Setup\Actions\RunSetup;
use App\Features\Setup\Events\CollectingPostUpgradeTasks;
use App\Features\Setup\PostUpgrade\BackfillPirepEvents;
use App\Features\Setup\PostUpgrade\ConvertSimBriefOfps;
use App\Features\Setup\PostUpgrade\ScanThemeForDeprecations;
use App\Features\Setup\Steps\ConvertToInnodb;
use App\Features\Setup\Steps\LinkBundledAddons;
use App\Features\Setup\Steps\MaintenanceDown;
use App\Features\Setup\Steps\MaintenanceUp;
use App\Features\Setup\Steps\Optimize;
use App\Features\Setup\Steps\PassportKeys;
use App\Features\Setup\Steps\Preflight;
use App\Features\Setup\Steps\PrepareCacheTables;
use App\Features\Setup\Steps\RefreshAddonCache;
use App\Features\Setup\Steps\ReloadOctane;
use App\Features\Setup\Steps\RunDataMigrations;
use App\Features\Setup\Steps\RunDataTasks;
use App\Features\Setup\Steps\RunMigrations;
use App\Features\Setup\Steps\SeedCore;
use App\Features\Setup\Steps\StorageLink;
use App\Features\Setup\Steps\WriteMarkers;
use App\Features\Setup\Support\PauseScheduleDuringSetup;
use App\Features\Setup\Support\PostUpgradeTaskState;
use App\Features\Setup\Support\SetupCache;
use App\Features\Setup\Support\SetupLock;
use App\Features\Setup\Support\SetupRunState;
use App\Features\Setup\Support\UpgradeCompleted;
use App\Features\Themes\Support\ThemeViewFinder;
use App\Http\Composers\PageLinksComposer;
use App\Http\Composers\VersionComposer;
use App\Http\Middleware\AdminGate;
use App\Http\Middleware\InstalledCheck;
use App\Http\Middleware\SetupGate;
use App\Models\Role;
use App\Models\User;
use App\Policies\Filament\ActivityPolicy;
use App\Services\AddonSettingService;
use App\Services\ImageUploadService;
use App\Services\ModuleService;
use App\Services\PermissionRegistry;
use App\Services\RouteForge\Contracts\LintRule;
use App\Services\RouteForge\LintRunner;
use App\Services\RouteForge\Rules\ExistingDuplicates;
use App\Services\RouteForge\Rules\L10BatchOver100;
use App\Services\RouteForge\Rules\L11AirportTimezoneMissing;
use App\Services\RouteForge\Rules\L1AircraftCapacity;
use App\Services\RouteForge\Rules\L2bTypeMismatch;
use App\Services\RouteForge\Rules\L2RangeMismatch;
use App\Services\RouteForge\Rules\L3EmptySubfleets;
use App\Services\RouteForge\Rules\L4DuplicateFlightNumbersInBatch;
use App\Services\RouteForge\Rules\L6OriginEqualsDestination;
use App\Services\RouteForge\Rules\L7SubfleetsHaveNoFares;
use App\Services\RouteForge\Rules\L8EventDatesOutsideWindow;
use App\Services\RouteForge\Rules\L9BatchOver50;
use App\Services\SettingService;
use App\Services\Theme\ActiveThemeService;
use App\Services\VersionService;
use App\Support\Branding;
use App\Support\Extensions\Facades\Extensions;
use App\Support\InstallMarker;
use App\Support\LegacyAbility;
use App\Support\LegacyEnumAliases;
use App\Support\LegacyModuleLinks;
use App\Support\Logging\JsonLog;
use App\Support\PirepView\PirepViewTabRegistry;
use App\Support\Settings\SettingsCacheRefresher;
use App\Support\Themes;
use App\Support\Units\Time;
use Barryvdh\LaravelIdeHelper\IdeHelperServiceProvider;
use Closure;
use Filafly\Icons\Phosphor\Enums\Phosphor;
use Filament\Actions\ExportAction;
use Filament\Forms\Components\DateTimePicker;
use Hidehalo\Nanoid\Client as NanoidClient;
use Igaster\LaravelTheme\Facades\Theme;
use Illuminate\Auth\Access\Response;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Foundation\AliasLoader;
use Illuminate\Foundation\Application;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Response as ResponseFacade;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Intervention\Image\ImageManager as InterventionImageManager;
use Laracasts\Flash\Flash;
use Livewire\Livewire;
use Opcodes\LogViewer\Facades\LogViewer;
use Override;
use PhpUnitsOfMeasure\Exception\NonStringUnitName;
use PhpUnitsOfMeasure\Exception\UnknownUnitOfMeasure;
use PhpUnitsOfMeasure\PhysicalQuantity\Temperature;
use Spatie\Activitylog\Models\Activity;
use Spatie\LaravelSettings\SettingsCache;
use Symfony\Component\Yaml\Yaml;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * @throws UnknownUnitOfMeasure
     * @throws NonStringUnitName
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);

        // Gates must also hold for Livewire updates from a page opened before setup was reset.
        Livewire::addPersistentMiddleware([SetupGate::class, AdminGate::class, InstalledCheck::class]);

        // Global Filament component defaults. Registered here rather than in a
        // panel's bootUsing() because provider boot writes to the base
        // ComponentManager that every request's scoped clone inherits from —
        // bootUsing() registrations land in one request's scope and can miss
        // Livewire update requests.
        //
        // Vendor ImportAction ships defaultColor('gray') but its ExportAction
        // sibling doesn't, so uncolored export buttons would fall back to
        // primary and fill dark on the hero band.
        ExportAction::configureUsing(static fn (ExportAction $action): ExportAction => $action->defaultColor('gray'));

        // Every date-bearing picker carries a calendar glyph at the right
        // edge of the field (mockup flight-edit.html's native date inputs).
        // Resolved at render time because TimePicker flips hasDate() off
        // only after this configuration runs.
        DateTimePicker::configureUsing(static fn (DateTimePicker $picker): DateTimePicker => $picker
            ->suffixIcon(static fn (DateTimePicker $component): ?Phosphor => $component->hasDate() ? Phosphor::CalendarLight : null));

        // The AddonSettingService memo of resolved addon handles is
        // request-scoped via config/octane.php 'flush', but a long-running queue
        // worker is not flushed per job. Settings values themselves need no
        // reset: the settings classes are scoped bindings, which Laravel
        // discards before every request and every queued job.
        Queue::before(static function (): void {
            app(AddonSettingService::class)->clearMemo();
        });

        $this->bootSettingsCache();

        // The cron_daily channel (storage/logs/cron.log) is reserved for the
        // scheduler and the queue worker. Switch the default log channel only
        // when one of those commands starts, rather than mutating it globally
        // in routes/console.php: under Octane that global mutation leaked into
        // every HTTP worker (octane:start is itself an Artisan command that
        // loads the console routes), sending web request logs to cron.log.
        // CommandStarting never fires for HTTP requests, so Octane workers keep
        // the default (daily) channel.
        Event::listen(CommandStarting::class, static function (CommandStarting $event): void {
            $cronCommands = ['schedule:run', 'schedule:work', 'queue:work', 'queue:listen'];

            if (in_array($event->command, $cronCommands, true)) {
                Log::setDefaultDriver('cron_daily');
            }
        });

        // Data migrations rewrite settings rows through the query builder,
        // which fires no model events, so nothing busts the values the
        // `setting()` helper has cached. The web Updater runs optimize:clear
        // after migrating; a CLI `migrate-data` run gets the same cache flush.
        Event::listen(CommandFinished::class, static function (CommandFinished $event): void {
            if ($event->command === 'migrate-data' && $event->exitCode === 0) {
                Cache::flush();
            }
        });

        Model::preventLazyLoading(!$this->app->isProduction());

        Paginator::useBootstrap();

        // Activity logging defaults to disabled for every request. The reset
        // lives in App\Http\Middleware\DisableActivityLoggingByDefault (wired
        // in bootstrap/app.php) instead of here, because Octane runs boot()
        // only once per worker — the per-request reset has to live in the
        // request pipeline. The middleware reapplies the default before any
        // opt-in (EnableActivityLogging middleware, Filament panel boot)
        // gets a chance to flip it on.

        /**
         * Nano ID string helpers, mirroring Laravel's Str::uuid()/Str::ulid().
         * Str::nanoid() generates an ID via the hidehalo/nanoid client using
         * the project's alphabet/length; Str::isNanoid() validates one.
         */
        Str::macro('nanoid', fn (int $length = BaseModel::ID_MAX_LENGTH): string => new NanoidClient($length)->formattedId(BaseModel::ID_ALPHABET, $length));

        Str::macro('isNanoid', fn (mixed $value): bool => is_string($value) && preg_match('/^['.BaseModel::ID_ALPHABET.']{'.BaseModel::ID_MAX_LENGTH.'}$/', $value) === 1);

        /**
         * Dual-projection render switch (Decision D2 in skylight-dashboard-slice/design.md).
         *
         * Usage in a controller:
         *   return response()->themed('Dashboard', 'dashboard.index', $presenter);
         *
         * - When the active theme is kind = "spa": returns an Inertia response
         *   using $presenter->toInertiaArray() as the props payload.
         * - Otherwise (kind = "blade" or absent): returns a Blade view with
         *   $presenter->toBladeArray() as the template data.
         *
         * The two projections share one data-gathering path (the presenter) but
         * are NOT required to be byte-identical: toBladeArray() returns the
         * legacy model-rich shape; toInertiaArray() returns a flat JSON-
         * serializable DTO. See spec: spa-theme-render-switch/spec.md.
         *
         * @param string                                                   $inertiaPage Inertia component name (e.g. 'Dashboard')
         * @param string                                                   $bladeView   Blade view name (e.g. 'dashboard.index')
         * @param object                                                   $presenter   Object with toInertiaArray() and toBladeArray()
         * @param array<int, RenderHook>|Closure(): array<int, RenderHook> $renderHooks
         *                                                                              Render hooks for the page. Dispatched, and their
         *                                                                              `vue` fills stored as slot entries, on the SPA path
         *                                                                              only; a Blade page dispatches nothing.
         */
        ResponseFacade::macro('themed', function (
            string $inertiaPage,
            string $bladeView,
            ?object $presenter = null,
            Closure|array|Arrayable $bladeData = [],
            Closure|array|Arrayable|null $spa = null,
            array|Closure $renderHooks = [],
        ) {
            $dispatchRenderHooks = static function () use ($renderHooks): void {
                foreach ($renderHooks instanceof Closure ? $renderHooks() : $renderHooks as $hook) {
                    Extensions::slots()->addAll($hook::name(), Hooks::filter($hook));
                }
            };

            // Legacy presenter path (one object exposing BOTH projections). Kept
            // for controllers not yet migrated.
            if ($presenter !== null && method_exists($presenter, 'toInertiaArray')) {
                if (theme_kind() === 'spa') {
                    $dispatchRenderHooks();

                    return Inertia::render($inertiaPage, $presenter->toInertiaArray());
                }

                return view($bladeView, theme_uses_spa_data() ? $presenter->toInertiaArray() : $presenter->toBladeArray());
            }

            // Direct path (no presenter): the controller supplies the Blade data and
            // the SPA props, each an array/Arrayable or a Closure. Only the ACTIVE
            // theme's payload is realised, so a Closure defers the other's cost
            // (e.g. the SPA DTO isn't built on the Blade path, and vice-versa).
            if (theme_kind() === 'spa') {
                $dispatchRenderHooks();

                return Inertia::render($inertiaPage, $spa instanceof Closure ? $spa() : ($spa ?? []));
            }

            // A Blade theme that opts in (theme.json "data": "spa") gets the
            // same page props as an SPA theme, as view variables.
            if (theme_uses_spa_data()) {
                return view($bladeView, $spa instanceof Closure ? $spa() : ($spa ?? []));
            }

            return view($bladeView, $bladeData instanceof Closure ? $bladeData() : $bladeData);
        });

        /**
         * Override the stock `eloquent` auth provider so a stale session cookie
         * over a fresh/wiped database (no users table yet) resolves to no user
         * instead of throwing during install. See InstallSafeUserProvider.
         */
        Auth::provider('eloquent', static fn ($app, array $config): InstallSafeUserProvider => new InstallSafeUserProvider($app['hash'], $config['model']));

        /**
         * Gates (i.e. Authentication) definition
         */
        // Super-admins bypass every permission/policy check. Replaces the
        // removed filament-shield super_admin gate. Return null (not false) so
        // non-super-admins fall through to the normal checks.
        Gate::before(static fn (?User $user): ?bool => $user?->hasRole(Role::superAdminName()) ? true : null);

        Gate::define('access_admin', static fn (?User $user): Response => $user?->hasAdminAccess()
            ? Response::allow()
            : Response::deny('You do not have permission to access this page.'));

        Gate::define('viewLogViewer', static fn (?User $user): Response => $user?->can('view-logs')
            ? Response::allow()
            : Response::deny('You do not have permission to access this page.'));

        Gate::policy(Activity::class, ActivityPolicy::class);

        // Aims to register the policies only if we are running in Filament cause they shouldn't be enforced outside of filament
        Gate::guessPolicyNamesUsing(static function (string $modelClass): ?string {
            if (filament()->isServing()) {
                // try to resolve policies under Filament
                $targetPolicy = str_replace('Models', 'Policies\\Filament', $modelClass).'Policy';

                // Return the policy if there is no, otherwise fallback on the default
                if (class_exists($targetPolicy)) {
                    return $targetPolicy;
                }
            }

            // follow the same namespace as the model
            $targetPolicy = str_replace('Models', 'Policies', $modelClass).'Policy';

            return class_exists($targetPolicy) ? $targetPolicy : null;
        });

        /**
         * Custom blade directives
         */
        Blade::directive('minutestotime', static fn (string $expr): string => sprintf('<?php echo '.Time::class.'::minutesToTimeString(%s); ?>', $expr));

        Blade::directive('minutestohours', static fn (string $expr): string => sprintf('<?php echo '.Time::class.'::minutesToHours(%s); ?>', $expr));

        Blade::directive('secstohhmm', static fn (string $expr): string => sprintf('<?php echo secstohhmm(%s); ?>', $expr));

        // 7.x themes gate admin links with laratrust's @ability.
        Blade::if('ability', static fn (string|array $roles, string|array $permissions = []): bool => LegacyAbility::check(Auth::user(), $roles, $permissions));

        /**
         * Create Measurements Aliases
         */
        Temperature::getUnit('F')->addAlias('f');
        Temperature::getUnit('C')->addAlias('c');

        /**
         * Data automatically injected in views
         */
        View::composer('admin.sidebar', VersionComposer::class);

        /** @noinspection LaravelUnknownViewInspection */
        View::composer('nav', PageLinksComposer::class);

        /** @noinspection LaravelUnknownViewInspection */
        View::composer('nav', function ($view): void {
            $view->with('languages', Config::get('phpvms.languages'));
            $view->with('locale', App::getLocale());
        });

        // 7.x themes build their menus from $moduleSvc.
        View::share('moduleSvc', $this->app->make(LegacyModuleLinks::class));

        /*
         * Bind the class used to fullfill the Metar class contract
         */
        $this->app->bind(
            Metar::class,
            config('phpvms.metar_lookup')
        );
    }

    /**
     * Scope the settings cache to the installed version, so the first request
     * after an upgrade never unserializes a settings object cached by the
     * previous version's classes, and reload the cached settings after
     * migrations, which write the settings tables directly.
     */
    private function bootSettingsCache(): void
    {
        // Read once per worker: the version only changes with a deploy, which
        // restarts the workers.
        $version = null;

        SettingsCache::resolvePrefixUsing(static function () use (&$version): string {
            if ($version === null) {
                try {
                    $version = app(VersionService::class)->getCurrentVersion();
                } catch (Throwable) {
                    $version = '';
                }
            }

            return $version;
        });

        Event::listen(MigrationsEnded::class, static function (): void {
            app(SettingsCacheRefresher::class)->refreshAll();
        });

        PauseScheduleDuringSetup::register();

        // The file channels write JSON lines; the admin log viewer needs a reader for them.
        LogViewer::extend('laravel_json', JsonLog::class);

        // Post-upgrade tasks run in the order added; listeners only add().
        Event::listen(CollectingPostUpgradeTasks::class, static function (CollectingPostUpgradeTasks $event): void {
            $event->add(app(BackfillPirepEvents::class));
            $event->add(app(ConvertSimBriefOfps::class));
            $event->add(app(ScanThemeForDeprecations::class));
        });
    }

    /**
     * Register any application services.
     */
    #[Override]
    public function register(): void
    {
        LegacyEnumAliases::register();
        $this->app->singleton(LegacyModuleLinks::class);

        // The cache store may be missing mid-setup, yet schedule:run and Livewire need one.
        // Skipped while a config cache is built, so the swap is never baked in.
        if (SetupCache::active() && !SetupCache::$buildingConfig && !$this->app->runningConsoleCommand('config:cache', 'optimize')) {
            SetupCache::$realStore = config('cache.default');
            config(['cache.default' => 'array']);
        }

        $this->app->singleton('igaster.themes', fn (): Themes => new Themes());

        $this->app->singleton('view.finder', fn (Application $app): ThemeViewFinder => new ThemeViewFinder(
            $app['files'],
            $app['config']['view.paths']
        ));

        $this->app->singleton(ModuleService::class);

        // Intervention registers `image` and aliases its manager class to that key,
        // but Laravel 13 binds `image` to its own Illuminate\Image\ImageManager, so
        // the alias hands out the wrong class. Bind the Intervention manager by
        // class so avatar resizing gets the one with canvas()/make().
        $this->app->bind(InterventionImageManager::class, fn (Application $app): InterventionImageManager => new InterventionImageManager([
            'driver' => $app->make(ImageUploadService::class)->rasterDriver() ?? 'gd',
        ]));
        $this->app->singleton(SocialiteProviderRegistry::class);
        $this->app->singleton(OAuthConnectionService::class);

        // Takes the data-migration installer back off Laravel's `migrate:install`
        // name, which it claims through an inherited $signature. See the command's
        // docblock -- without this, every migrate run builds the wrong table.
        // Rebinding the package's own container key is enough: ServiceProvider
        // ::commands() resolves these lazily when the console kernel starts, which
        // is after every provider has registered.
        $this->app->singleton(
            'command.migrate-data.install',
            fn (Application $app): DataMigrationInstallCommand => new DataMigrationInstallCommand($app['migration.data.repository'])
        );

        // The "this site is installed" marker file. Stateless/Octane-safe;
        // bound as a singleton so tests can swap the path.
        $this->app->singleton(InstallMarker::class, fn (): InstallMarker => new InstallMarker(InstallMarker::defaultPath()));

        // The unfinished-run file and the run lock of the update procedure.
        // The lock is bound per resolution: each holder needs its own file handle.
        $this->app->singleton(SetupRunState::class, fn (): SetupRunState => new SetupRunState(SetupRunState::defaultPath()));
        $this->app->bind(SetupLock::class, fn (): SetupLock => new SetupLock(SetupLock::defaultPath()));
        $this->app->singleton(UpgradeCompleted::class, fn (): UpgradeCompleted => new UpgradeCompleted(UpgradeCompleted::defaultPath()));

        $this->app->tag([Preflight::class, MaintenanceDown::class, MaintenanceUp::class, ConvertToInnodb::class, PrepareCacheTables::class, LinkBundledAddons::class, RunMigrations::class, RunDataMigrations::class, RunDataTasks::class, SeedCore::class, PassportKeys::class, StorageLink::class, WriteMarkers::class, RefreshAddonCache::class, Optimize::class, ReloadOctane::class], 'setup.steps');
        $this->app->bind(RunSetup::class, fn (Application $app): RunSetup => new RunSetup(
            $app->make(SetupRunState::class),
            $app->make(SetupLock::class),
            $app->tagged('setup.steps'),
            $app->make(UpgradeCompleted::class),
        ));
        // Its own lock: `phpvms:backfill-pirep-events` runs the same task outside a setup run.
        $this->app->bind(RunPostUpgradeTasks::class, fn (Application $app): RunPostUpgradeTasks => new RunPostUpgradeTasks(
            $app->make(PostUpgradeTaskState::class),
            new SetupLock(RunPostUpgradeTasks::lockPath()),
            $app->make('events'),
        ));

        // Core settings by key. Stateless (values live in the scoped settings
        // classes); bound as a singleton purely for reuse.
        $this->app->singleton(SettingService::class);

        // Per-addon settings by handle. Holds a per-request memo of resolved
        // handles, discarded through config/octane.php 'flush'.
        $this->app->singleton(AddonSettingService::class);

        // Airline branding resolution. Stateless/Octane-safe (see class
        // docblock); bound as a singleton purely for reuse, not caching.
        $this->app->singleton(Branding::class);

        // Central admin image-upload conversion. Stateless/Octane-safe, no
        // memo to flush; bound as a singleton purely for reuse.
        $this->app->singleton(ImageUploadService::class);

        // Permission registry: modules register custom permissions into the
        // same instance during boot(), so it must be a singleton.
        $this->app->singleton(PermissionRegistry::class);

        // Asset kinds: same shape as the permission registry above. Core seeds
        // only `image`, the one kind it serves itself, and a module registers
        // the kinds it ships — sounds, gauges, paintkits — into this instance
        // during boot(). A PHP enum could never allow that, which is why this
        // is a registry.
        $this->app->singleton(AssetTypes::class);

        // Memoises find() per (slot, key) for the life of a request (see
        // AssetService::$findMemo). Scoped, not singleton: OperationTerminated
        // already flushes scoped bindings (config/octane.php), so Octane
        // starts every request with an empty memo instead of one worker's
        // lookups leaking into the next request it serves.
        $this->app->scoped(AssetService::class);

        // Memoises the active theme revision per request (see
        // ActiveThemeService::$revisions). Scoped for the same reason as
        // AssetService above: Octane starts each request with an empty memo.
        $this->app->scoped(ActiveThemeService::class);

        $this->app->singleton(PirepViewTabRegistry::class);

        // RouteForge lint catalog: tag every concrete rule class so adding a
        // rule means appending one entry here, not editing LintRunner. The
        // bind below materializes the tagged generator into the runner's
        // `$rules` array.
        $this->app->tag([
            L1AircraftCapacity::class,
            L2RangeMismatch::class,
            L2bTypeMismatch::class,
            L3EmptySubfleets::class,
            L4DuplicateFlightNumbersInBatch::class,
            // ExistingDuplicates emits L5 (ERROR same-bundle) + L12 (WARNING
            // cross-bundle) issues from one merged query, replacing the
            // separate L5ExistingDuplicate + L12ExistingDuplicateCrossBundle
            // rules from the pre-cleanup catalog.
            ExistingDuplicates::class,
            L6OriginEqualsDestination::class,
            L7SubfleetsHaveNoFares::class,
            L8EventDatesOutsideWindow::class,
            L9BatchOver50::class,
            L10BatchOver100::class,
            L11AirportTimezoneMissing::class,
        ], 'routeforge.lint_rules');

        $this->app->bind(
            static function ($app): LintRunner {
                /** @var iterable<LintRule> $tagged */
                $tagged = $app->tagged('routeforge.lint_rules');

                return new LintRunner(iterator_to_array($tagged, preserve_keys: false));
            },
        );

        // Load the aliases
        $loader = AliasLoader::getInstance();
        $aliases = [
            'Carbon' => Carbon::class,
            'Flash'  => Flash::class,
            'Theme'  => Theme::class,
            'Yaml'   => Yaml::class,

            // ENUMS
            'ActiveState' => ActiveState::class,
            'UserState'   => UserState::class,
            'PirepSource' => PirepSource::class,
            'PirepState'  => PirepState::class,
            'PirepPhase'  => PirepPhase::class,
            'PirepStatus' => PirepPhase::class,
        ];

        foreach ($aliases as $alias => $class) {
            $loader->alias($alias, $class);
        }

        // Only load the IDE helper if it's included and enabled
        /* @noinspection NestedPositiveIfStatementsInspection */
        if (config('app.debug') === true && class_exists(IdeHelperServiceProvider::class)) {
            $this->app->register(IdeHelperServiceProvider::class);
        }
    }
}

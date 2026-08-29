<?php

namespace App\Providers;

use App\Auth\InstallSafeUserProvider;
use App\Contracts\Metar;
use App\Contracts\Model as BaseModel;
use App\Enums\ActiveState;
use App\Enums\PirepPhase;
use App\Enums\PirepSource;
use App\Enums\PirepState;
use App\Enums\UserState;
use App\Features\Assets\AssetService;
use App\Features\Assets\AssetTypes;
use App\Http\Composers\PageLinksComposer;
use App\Http\Composers\VersionComposer;
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
use App\Support\Branding;
use App\Support\PirepView\PirepViewTabRegistry;
use App\Support\ThemeViewFinder;
use App\Support\Units\Time;
use Barryvdh\LaravelIdeHelper\IdeHelperServiceProvider;
use Closure;
use Filafly\Icons\Phosphor\Enums\Phosphor;
use Filament\Actions\ExportAction;
use Filament\Forms\Components\DateTimePicker;
use Hidehalo\Nanoid\Client as NanoidClient;
use Igaster\LaravelTheme\Facades\Theme;
use Illuminate\Auth\Access\Response;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\AliasLoader;
use Illuminate\Foundation\Application;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
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
use Laracasts\Flash\Flash;
use Override;
use PhpUnitsOfMeasure\Exception\NonStringUnitName;
use PhpUnitsOfMeasure\Exception\UnknownUnitOfMeasure;
use PhpUnitsOfMeasure\PhysicalQuantity\Temperature;
use SocialiteProviders\Discord\Provider;
use SocialiteProviders\Manager\SocialiteWasCalled;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\Yaml\Yaml;

class AppServiceProvider extends ServiceProvider
{
    /**
     * @throws UnknownUnitOfMeasure
     * @throws NonStringUnitName
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);

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

        // The SettingService memo is request-scoped via config/octane.php 'flush',
        // but a long-running queue worker is not flushed per job. Reset the memo
        // before each job so a worker observes settings changed by other
        // processes — otherwise a stale memoized value could be written back into
        // the shared cache on the next Cache::remember() miss.
        Queue::before(static fn () => app(SettingService::class)->clearMemo());

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
         * @param string $inertiaPage Inertia component name (e.g. 'Dashboard')
         * @param string $bladeView   Blade view name (e.g. 'dashboard.index')
         * @param object $presenter   Object with toInertiaArray() and toBladeArray()
         */
        ResponseFacade::macro('themed', function (
            string $inertiaPage,
            string $bladeView,
            ?object $presenter = null,
            Closure|array|Arrayable $bladeData = [],
            Closure|array|Arrayable|null $spa = null,
        ) {
            // Legacy presenter path (one object exposing BOTH projections). Kept
            // for controllers not yet migrated.
            if ($presenter !== null && method_exists($presenter, 'toInertiaArray')) {
                return theme_kind() === 'spa'
                    ? Inertia::render($inertiaPage, $presenter->toInertiaArray())
                    : view($bladeView, $presenter->toBladeArray());
            }

            // Direct path (no presenter): the controller supplies the Blade data and
            // the SPA props, each an array/Arrayable or a Closure. Only the ACTIVE
            // theme's payload is realised, so a Closure defers the other's cost
            // (e.g. the SPA DTO isn't built on the Blade path, and vice-versa).
            if (theme_kind() === 'spa') {
                return Inertia::render($inertiaPage, $spa instanceof Closure ? $spa() : ($spa ?? []));
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

        /*
         * Bind the class used to fullfill the Metar class contract
         */
        $this->app->bind(
            Metar::class,
            config('phpvms.metar_lookup')
        );

        /**
         * OAuth providers events registration
         */
        Event::listen(function (SocialiteWasCalled $event): void {
            $event->extendSocialite('discord', Provider::class);
            $event->extendSocialite('ivao', \SocialiteProviders\Ivao\Provider::class);
            $event->extendSocialite('vatsim', \SocialiteProviders\Vatsim\Provider::class);
        });
    }

    /**
     * Register any application services.
     */
    #[Override]
    public function register(): void
    {
        $this->app->singleton('view.finder', fn (Application $app): ThemeViewFinder => new ThemeViewFinder(
            $app['files'],
            $app['config']['view.paths']
        ));

        $this->app->singleton(ModuleService::class);

        // Core settings read/write. Bound as a singleton so the per-request
        // Tier-1 memo ($memo array) is shared across all setting() calls within
        // one request. Registered in config/octane.php 'flush' so Octane
        // discards the instance (empty memo) before each new request.
        $this->app->singleton(SettingService::class);

        // Per-addon settings read/write. Stateless/Octane-safe; bound as a
        // singleton so the `addon_setting()` helper reuses one instance.
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
            LintRunner::class,
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

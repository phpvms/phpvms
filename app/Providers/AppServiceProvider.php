<?php

namespace App\Providers;

use App\Contracts\Metar;
use App\Contracts\Model as BaseModel;
use App\Enums\ActiveState;
use App\Enums\PirepSource;
use App\Enums\PirepState;
use App\Enums\PirepStatus;
use App\Enums\UserState;
use App\Http\Composers\PageLinksComposer;
use App\Http\Composers\VersionComposer;
use App\Models\User;
use App\Notifications\Channels\Discord\DiscordWebhook;
use App\Policies\Filament\ActivityPolicy;
use App\Services\ModuleService;
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
use App\Support\ThemeViewFinder;
use App\Support\Units\Time;
use Barryvdh\LaravelIdeHelper\IdeHelperServiceProvider;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Hidehalo\Nanoid\Client as NanoidClient;
use Igaster\LaravelTheme\Facades\Theme;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\AliasLoader;
use Illuminate\Foundation\Application;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
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
         * Inject the extra display + monospace fonts used by the docs design
         * (Encode Sans for headings, Geist Mono + JetBrains Mono for code).
         * Served via Bunny Fonts (GDPR-compliant Google Fonts mirror — same
         * family names, same files, no Google CDN call). The body font (Geist)
         * is loaded by each panel via ->font('Geist'), which also writes
         * Filament's --font-family CSS variable. Family + weight list mirrors
         * docs/src/css/custom.css.
         */
        FilamentView::registerRenderHook(
            PanelsRenderHook::HEAD_END,
            static fn (): string => <<<'HTML'
                <link rel="stylesheet" href="https://fonts.bunny.net/css?family=encode-sans:500,600,700|geist-mono:400,500|jetbrains-mono:400,500,600&display=swap">
                HTML,
        );

        /**
         * Nano ID string helpers, mirroring Laravel's Str::uuid()/Str::ulid().
         * Str::nanoid() generates an ID via the hidehalo/nanoid client using
         * the project's alphabet/length; Str::isNanoid() validates one.
         */
        Str::macro('nanoid', fn (int $length = BaseModel::ID_MAX_LENGTH): string => (new NanoidClient($length))->formattedId(BaseModel::ID_ALPHABET, $length));

        Str::macro('isNanoid', fn (mixed $value): bool => is_string($value) && preg_match('/^['.BaseModel::ID_ALPHABET.']{'.BaseModel::ID_MAX_LENGTH.'}$/', $value) === 1);

        Notification::extend('discord_webhook', fn ($app) => app(DiscordWebhook::class));

        /**
         * Gates (i.e. Authentication) definition
         */
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
        View::share('moduleSvc', app(ModuleService::class));
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
            'PirepStatus' => PirepStatus::class,
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

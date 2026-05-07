<?php

namespace App\Providers;

use App\Contracts\AirportLookup;
use App\Contracts\Metar;
use App\Http\Composers\PageLinksComposer;
use App\Http\Composers\VersionComposer;
use App\Models\Enums\ActiveState;
use App\Models\Enums\PirepSource;
use App\Models\Enums\PirepState;
use App\Models\Enums\PirepStatus;
use App\Models\Enums\UserState;
use App\Models\User;
use App\Notifications\Channels\Discord\DiscordWebhook;
use App\Policies\Filament\ActivityPolicy;
use App\Services\ModuleService;
use App\Support\ThemeViewFinder;
use App\Support\Units\Time;
use Barryvdh\LaravelIdeHelper\IdeHelperServiceProvider;
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
use Laracasts\Flash\Flash;
use PhpUnitsOfMeasure\Exception\NonStringUnitName;
use PhpUnitsOfMeasure\Exception\UnknownUnitOfMeasure;
use PhpUnitsOfMeasure\PhysicalQuantity\Temperature;
use SocialiteProviders\Discord\Provider;
use SocialiteProviders\Manager\SocialiteWasCalled;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\Yaml\Yaml;
use VaCentral\Contracts\IVaCentral;
use VaCentral\VaCentral;

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

        activity()->disableLogging();

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
        View::composer('nav', PageLinksComposer::class);
        View::composer('admin.sidebar', VersionComposer::class);
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

        /*
         * Bind the class used to fullfill the AirportLookup class contract
         */
        $this->app->bind(
            AirportLookup::class,
            config('phpvms.airport_lookup')
        );

        $this->app->bind(
            IVaCentral::class,
            function ($app): VaCentral {
                $client = new VaCentral();

                // Set API if exists
                if (filled(config('vacentral.api_key'))) {
                    $client->setApiKey(config('vacentral.api_key'));
                }

                return $client;
            }
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
    #[\Override]
    public function register(): void
    {
        $this->app->singleton('view.finder', fn (Application $app): ThemeViewFinder => new ThemeViewFinder(
            $app['files'],
            $app['config']['view.paths']
        ));

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
            /* @noinspection PhpFullyQualifiedNameUsageInspection */
            $this->app->register(IdeHelperServiceProvider::class);
        }
    }
}

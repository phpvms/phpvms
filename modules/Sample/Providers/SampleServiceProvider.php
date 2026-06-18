<?php

namespace Modules\Sample\Providers;

use Config;
use Illuminate\Support\ServiceProvider;
use Override;
use Route;

class SampleServiceProvider extends ServiceProvider
{
    /**
     * Boot the application events.
     *
     * The module's admin UI lives in its own Filament panel — see
     * SampleAdminPanelProvider. There is no admin/frontend "link" registration.
     */
    public function boot(): void
    {
        $this->registerRoutes();
        $this->registerTranslations();
        $this->registerConfig();
        $this->registerViews();

        $this->loadMigrationsFrom(__DIR__.'/../Database/migrations');
    }

    /**
     * Register the service provider.
     */
    #[Override]
    public function register(): void
    {
        //
    }

    /**
     * Register the routes
     */
    protected function registerRoutes()
    {
        /*
         * Routes for the frontend
         */
        Route::group([
            'as'     => 'sample.',
            'prefix' => 'sample',
            // If you want a RESTful module, change this to 'api'
            'middleware' => ['web'],
            'namespace'  => 'Modules\Sample\Http\Controllers',
        ], function (): void {
            $this->loadRoutesFrom(__DIR__.'/../Http/Routes/web.php');
        });

        /*
         * Routes for an API
         */
        Route::group([
            'as'     => 'sample.',
            'prefix' => 'api/sample',
            // If you want a RESTful module, change this to 'api'
            'middleware' => ['api'],
            'namespace'  => 'Modules\Sample\Http\Controllers\Api',
        ], function (): void {
            $this->loadRoutesFrom(__DIR__.'/../Http/Routes/api.php');
        });
    }

    /**
     * Register config.
     */
    protected function registerConfig()
    {
        $this->publishes([
            __DIR__.'/../Config/config.php' => config_path('sample.php'),
        ], 'config');

        $this->mergeConfigFrom(
            __DIR__.'/../Config/config.php', 'sample'
        );
    }

    /**
     * Register views.
     */
    public function registerViews(): void
    {
        $viewPath = resource_path('views/modules/sample');
        $sourcePath = __DIR__.'/../Resources/views';

        $this->publishes([
            $sourcePath => $viewPath,
        ], 'views');

        $paths = array_map(
            fn (string $path): string => $path.'/modules/sample',
            Config::get('view.paths')
        );

        $paths[] = $sourcePath;
        $this->loadViewsFrom($paths, 'sample');
    }

    /**
     * Register translations.
     */
    public function registerTranslations(): void
    {
        $langPath = resource_path('lang/modules/sample');

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, 'sample');
        } else {
            $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'sample');
        }
    }

    /**
     * Get the services provided by the provider.
     */
    #[Override]
    public function provides(): array
    {
        return [];
    }
}

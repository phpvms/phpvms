<?php

namespace Modules\Sample\Providers;

use App\Services\ModuleService;
use Illuminate\Support\ServiceProvider;
use Route;

class SampleServiceProvider extends ServiceProvider
{
    protected $moduleSvc;

    /**
     * Boot the application events.
     */
    public function boot()
    {
        $this->moduleSvc = app(ModuleService::class);

        $this->registerRoutes();
        $this->registerTranslations();
        $this->registerConfig();
        $this->registerViews();

        $this->registerLinks();

        $this->loadMigrationsFrom(__DIR__.'/../Database/migrations');
    }

    /**
     * Register the service provider.
     */
    public function register()
    {
        //
    }

    /**
     * Add module links here
     */
    public function registerLinks()
    {
        // Show this link if logged in
        // $this->moduleSvc->addFrontendLink('Sample', '/sample', '', $logged_in=true);

        // Admin links:
        $this->moduleSvc->addAdminLink('Sample', '/admin/sample');
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
        ], function () {
            $this->loadRoutesFrom(__DIR__.'/../Http/Routes/web.php');
        });

        /*
         * Routes for the admin
         */
        Route::group([
            'as'     => 'sample.',
            'prefix' => 'admin/sample',
            // If you want a RESTful module, change this to 'api'
            'middleware' => ['web', 'role:admin'],
            'namespace'  => 'Modules\Sample\Http\Controllers\Admin',
        ], function () {
            $this->loadRoutesFrom(__DIR__.'/../Http/Routes/admin.php');
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
        ], function () {
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
    public function registerViews()
    {
        $viewPath = resource_path('views/modules/sample');
        $sourcePath = __DIR__.'/../Resources/views';

        $this->publishes([
            $sourcePath => $viewPath,
        ], 'views');

        $paths = array_map(
            function ($path) {
                return $path.'/modules/sample';
            },
            \Config::get('view.paths')
        );

        $paths[] = $sourcePath;
        $this->loadViewsFrom($paths, 'sample');
    }

    /**
     * Register translations.
     */
    public function registerTranslations()
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
    public function provides(): array
    {
        return [];
    }
}

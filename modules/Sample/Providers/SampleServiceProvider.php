<?php

namespace Modules\Sample\Providers;

use App\Contracts\Addons\HasSettings;
use Config;
use Illuminate\Support\ServiceProvider;
use Override;
use Route;

class SampleServiceProvider extends ServiceProvider implements HasSettings
{
    /**
     * Declare the module's settings.
     *
     * These are synced into the `addon_settings` table on boot and editable
     * from the Settings page inside the Sample panel. Read a value anywhere
     * with `addon_setting('sample', '<key>')` — see sample_setting() in
     * this module's helpers.php for a usage example.
     *
     * @return array<int, array<string, mixed>>
     */
    public function settings(): array
    {
        return [
            [
                'key'         => 'greeting',
                'name'        => 'Greeting',
                'default'     => 'Hello from the Sample module!',
                'group'       => 'general',
                'type'        => 'text',
                'options'     => '',
                'description' => 'Text returned by sample_module_greeting()',
                'order'       => 0,
            ],
            [
                'key'         => 'enabled',
                'name'        => 'Feature Enabled',
                'default'     => 'true',
                'group'       => 'general',
                'type'        => 'boolean',
                'options'     => 'true,false',
                'description' => 'Toggle the sample feature on or off',
                'order'       => 1,
            ],
            [
                'key'         => 'max_items',
                'name'        => 'Max Items',
                'default'     => '10',
                'group'       => 'limits',
                'type'        => 'int',
                'options'     => '',
                'description' => 'Maximum number of sample items to show',
                'order'       => 0,
            ],
            [
                'key'         => 'threshold',
                'name'        => 'Threshold',
                'default'     => '0.5',
                'group'       => 'limits',
                'type'        => 'float',
                'options'     => '',
                'description' => 'A floating-point threshold value',
                'order'       => 1,
            ],
            [
                'key'         => 'mode',
                'name'        => 'Mode',
                'default'     => 'auto',
                'group'       => 'limits',
                'type'        => 'select',
                'options'     => 'auto,manual,off',
                'description' => 'Operating mode for the sample feature',
                'order'       => 2,
            ],
        ];
    }

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

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
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

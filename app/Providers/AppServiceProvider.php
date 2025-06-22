<?php

namespace App\Providers;

use App\Notifications\Channels\Discord\DiscordWebhook;
use App\Services\ModuleService;
use App\Support\ThemeViewFinder;
use App\Support\Utils;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Schema::defaultStringLength(191);

        Paginator::useBootstrap();
        View::share('moduleSvc', app(ModuleService::class));

        activity()->disableLogging();

        Notification::extend('discord_webhook', fn ($app) => app(DiscordWebhook::class));
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton('view.finder', fn (array $app): \App\Support\ThemeViewFinder => new ThemeViewFinder(
            $app['files'],
            $app['config']['view.paths'],
            null
        ));

        // Only load the IDE helper if it's included and enabled
        if (config('app.debug') === true) {
            /* @noinspection NestedPositiveIfStatementsInspection */
            /* @noinspection PhpFullyQualifiedNameUsageInspection */
            if (class_exists(\Barryvdh\LaravelIdeHelper\IdeHelperServiceProvider::class)) {
                /* @noinspection PhpFullyQualifiedNameUsageInspection */
                $this->app->register(\Barryvdh\LaravelIdeHelper\IdeHelperServiceProvider::class);
            }

            if (config('app.debug_toolbar') === true) {
                Utils::enableDebugToolbar();
            } else {
                Utils::disableDebugToolbar();
            }
        }
    }
}

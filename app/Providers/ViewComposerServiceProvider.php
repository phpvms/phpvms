<?php

namespace App\Providers;

use App\Http\Composers\PageLinksComposer;
use App\Http\Composers\VersionComposer;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class ViewComposerServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        View::composer('nav', PageLinksComposer::class);
        View::composer('admin.sidebar', VersionComposer::class);
        View::composer('nav', function ($view): void {
            $view->with('languages', Config::get('languages'));
            $view->with('locale', App::getLocale());
        });
    }
}

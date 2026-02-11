<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * Define the routes for the application.
     *
     * @return void
     */
    public function map()
    {
        // Load the web routes
        Route::middleware('web')
            ->group(function () {
                $this->loadRoutesFrom(__DIR__.'/../../routes/web.php');
            });

        // Load the api routes
        Route::group([
            'middleware' => ['api'],
            'prefix'     => 'api',
            'as'         => 'api.',
        ], function () {
            $this->loadRoutesFrom(__DIR__.'/../../routes/api.php');
        });
    }
}

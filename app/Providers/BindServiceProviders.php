<?php

namespace App\Providers;

use App\Contracts\AirportLookup;
use App\Contracts\Metar;
use Illuminate\Support\ServiceProvider;
use VaCentral\VaCentral;

class BindServiceProviders extends ServiceProvider
{
    public function boot(): void
    {
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
            function ($app): \VaCentral\Contracts\IVaCentral {
                $client = new VaCentral();

                // Set API if exists
                if (filled(config('vacentral.api_key'))) {
                    $client->setApiKey(config('vacentral.api_key'));
                }

                return $client;
            }
        );
    }
}

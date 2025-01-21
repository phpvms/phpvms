<?php

namespace App\Widgets;

use App\Contracts\Widget;
use App\Services\AirportService;

/**
 * This is a widget for the 3rd party CheckWX service
 */
class Weather extends Widget
{
    protected $config = [
        'icao'     => null,
        'raw_only' => null,
    ];

    /**
     * Attempt to get the data from the CheckWX API
     */
    public function run()
    {
        /** @var \App\Services\AirportService $airportSvc */
        $airportSvc = app(AirportService::class);
        $metar = $airportSvc->getMetar($this->config['icao']);
        $taf = $airportSvc->getTaf($this->config['icao']);

        return view('widgets.weather', [
            'config'    => $this->config,
            'metar'     => $metar,
            'taf'       => $taf,
            'unit_alt'  => setting('units.altitude'),
            'unit_dist' => setting('units.distance'),
            'unit_temp' => setting('units.temperature'),
        ]);
    }
}

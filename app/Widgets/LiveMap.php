<?php

namespace App\Widgets;

use App\Contracts\Widget;
use App\Repositories\AcarsRepository;
use App\Services\GeoService;

/**
 * Show the live map in a view
 */
class LiveMap extends Widget
{
    protected $config = [
        'height' => '800px',
        'width'  => '100%',
        'table'  => true,
    ];

    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function run()
    {
        $geoSvc = app(GeoService::class);
        $acarsRepo = app(AcarsRepository::class);

        $pireps = $acarsRepo->getPositions(setting('acars.live_time', 0));
        $positions = $geoSvc->getFeatureForLiveFlights($pireps);

        $center_coords = setting('acars.center_coords', '0,0');
        $center_coords = array_map(function ($c) {
            return (float) trim($c);
        }, explode(',', $center_coords));

        return view('widgets.live_map', [
            'config'    => $this->config,
            'pireps'    => $pireps,
            'positions' => $positions,
            'center'    => $center_coords,
            'zoom'      => setting('acars.default_zoom', 5),
        ]);
    }
}

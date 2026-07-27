<?php

namespace App\Widgets;

use App\Contracts\Widget;
use App\Models\Pirep;
use App\Services\GeoService;
use Illuminate\Contracts\View\Factory;
use Illuminate\View\View;

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
     * @return Factory|View
     */
    public function run(): Factory|\Illuminate\Contracts\View\View
    {
        $geoSvc = app(GeoService::class);

        // The same source the API endpoints use. This widget calls the scope
        // directly rather than going through the API, so it had to be moved too
        // or it would have kept rendering from `acars`.
        $pireps = Pirep::onLiveMap()->get();
        $positions = $geoSvc->getFeatureForLiveFlights($pireps);

        $center_coords = setting('livemap.center_coords', '0,0');
        $center_coords = array_map(fn ($c): float => (float) trim($c), explode(',', $center_coords));

        return view('widgets.live_map', [
            'config'    => $this->config,
            'pireps'    => $pireps,
            'positions' => $positions,
            'center'    => $center_coords,
            'zoom'      => setting('livemap.default_zoom', 5),
        ]);
    }
}

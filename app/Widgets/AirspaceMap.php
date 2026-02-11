<?php

namespace App\Widgets;

use App\Contracts\Widget;
use Illuminate\Contracts\View\Factory;
use Illuminate\View\View;

/**
 * Show the live map in a view
 */
class AirspaceMap extends Widget
{
    protected $config = [
        'height' => '800px',
        'width'  => '100%',
        'lat'    => 0,
        'lon'    => 0,
    ];

    /**
     * @return Factory|View
     */
    public function run()
    {
        return view('widgets.airspace_map', [
            'config' => $this->config,
        ]);
    }
}

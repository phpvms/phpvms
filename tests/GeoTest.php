<?php

namespace Tests;

use App\Models\Navdata;
use Exception;
use Illuminate\Foundation\Testing\WithoutMiddleware;

final class GeoTest extends TestCase
{
    use WithoutMiddleware;

    public function test_closest_point(): void
    {
        $geoSvc = app('\App\Services\GeoService');

        /**
         * [2017-12-21 00:54:10] dev.INFO: Looking for ATL
         * [2017-12-21 00:54:10] dev.INFO: ATL - 36.58106 x 26.375603
         * [2017-12-21 00:54:10] dev.INFO: Looking for SIE
         * [2017-12-21 00:54:10] dev.INFO: found 3 for SIE
         * [2017-12-21 00:54:10] dev.INFO: name: SIE - 39.0955x-74.800344
         * [2017-12-21 00:54:10] dev.INFO: name: SIE - 41.15169x-3.604667
         * [2017-12-21 00:54:10] dev.INFO: name: SIE - 52.15527x22.200833
         */
        // Start at ATL
        $start_point = [36.58106, 26.375603];

        // These are all SIE
        $potential_points = [
            [39.0955, -74.800344],
            [41.15169, -3.604667],
            [52.15527, 22.200833],
        ];

        $coords = $geoSvc->getClosestCoords($start_point, $potential_points);
        $this->assertEquals([52.15527, 22.200833], $coords);
    }

    /**
     * Make sure the departure airports/sid/star are all filtered out
     *
     * @throws Exception
     */
    public function test_get_coords(): void
    {
        $geoSvc = app('\App\Services\GeoService');

        $route = [];
        $nav_count = random_int(5, 20);
        $navpoints = Navdata::factory()->count($nav_count)->create();
        foreach ($navpoints as $point) {
            $route[] = $point->id;
        }

        $route_str = 'KAUS SID '.implode(' ', $route).' STAR KJFK';
        $coords = $geoSvc->getCoordsFromRoute('KAUS', 'KJFK', [0, 0], $route_str);
        $this->assertCount($nav_count, $coords);
    }
}

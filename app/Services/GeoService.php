<?php

namespace App\Services;

use App\Contracts\Service;
use App\Models\Acars;
use App\Models\Enums\AcarsType;
use App\Models\Flight;
use App\Models\GeoJson;
use App\Models\Pirep;
use App\Repositories\AcarsRepository;
use App\Repositories\NavdataRepository;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use League\Geotools\Coordinate\Coordinate;
use League\Geotools\Geotools;

class GeoService extends Service
{
    /**
     * GeoService constructor.
     */
    public function __construct(
        private readonly AcarsRepository $acarsRepo,
        private readonly NavdataRepository $navRepo
    ) {}

    /**
     * Determine the closest set of coordinates from the starting position
     *
     * @param  array $coordStart
     * @return mixed
     *
     * @throws \League\Geotools\Exception\InvalidArgumentException
     */
    public function getClosestCoords($coordStart, array $all_coords)
    {
        $distance = [];
        $geotools = new Geotools();
        $start = new Coordinate($coordStart);

        foreach ($all_coords as $coords) {
            $coord = new Coordinate($coords);
            $dist = $geotools->distance()->setFrom($start)->setTo($coord);
            $distance[] = $dist->greatCircle();
        }

        $distance = collect($distance);
        $min = $distance->min();

        return $all_coords[$distance->search($min, true)];
    }

    /**
     * Pass in a route string, with the departure/arrival airports, and the
     * starting coordinates. Return the route points that have been found
     * from the `navdata` table
     *
     * @param $dep_icao     string  ICAO to ignore
     * @param $arr_icao     string  ICAO to ignore
     * @param $start_coords array   Starting point, [x, y]
     * @param $route        string  Textual route
     */
    public function getCoordsFromRoute($dep_icao, $arr_icao, $start_coords, $route): array
    {
        $coords = [];
        $filter_points = [$dep_icao, $arr_icao, 'SID', 'STAR'];

        $split_route = collect(explode(' ', (string) $route))->transform(function ($point) {
            if ($point === '' || $point === '0') {
                return false;
            }

            return strtoupper(trim($point));
        })->filter(fn ($point): bool => $point !== '' && $point !== '0' && !\in_array($point, $filter_points, true));

        /**
         * @var $split_route Collection
         * @var $route_point Acars
         */
        foreach ($split_route as $route_point) {
            Log::debug('Looking for '.$route_point);

            try {
                $points = $this->navRepo->findWhere(['id' => $route_point]);
            } catch (ModelNotFoundException) {
                continue;
            } catch (\Exception $e) {
                Log::error($e);

                continue;
            }

            $point = null;
            $size = \count($points);

            if ($size === 0) {
                continue;
            }

            if ($size === 1) {
                $point = $points[0];
                Log::debug('name: '.$point->id.' - '.$point->lat.'x'.$point->lon);
                $coords[] = $point;

                continue;
            }

            // Find the point with the shortest distance
            Log::info('found '.$size.' for '.$route_point);

            // Get the start point and then reverse the lat/lon reference
            // If the first point happens to have multiple possibilities, use
            // the starting point that was passed in
            if ($coords !== []) {
                $start_point = $coords[\count($coords) - 1];
                $start_point = [$start_point->lat, $start_point->lon];
            } else {
                $start_point = $start_coords;
            }

            // Put all of the lat/lon sets into an array to pick of what's clsest
            // to the starting point
            $potential_coords = [];
            foreach ($points as $point) {
                $potential_coords[] = [$point->lat, $point->lon];
            }

            // returns an array with the closest lat/lon to start point
            $closest_coords = $this->getClosestCoords($start_point, $potential_coords);
            foreach ($points as $point) {
                if ($point->lat === $closest_coords[0] && $point->lon === $closest_coords[1]) {
                    break;
                }
            }

            if ($point === null) {
                continue;
            }

            $coords[] = $point;
        }

        return $coords;
    }

    /**
     * Determine the center point between two sets of coordinates
     *
     *
     *
     * @throws \League\Geotools\Exception\InvalidArgumentException
     */
    public function getCenter($latA, $lonA, $latB, $lonB): array
    {
        $geotools = new Geotools();
        $coordA = new Coordinate([$latA, $lonA]);
        $coordB = new Coordinate([$latB, $lonB]);

        $vertex = $geotools->vertex()->setFrom($coordA)->setTo($coordB);
        $middlePoint = $vertex->middle();

        return [
            $middlePoint->getLatitude(),
            $middlePoint->getLongitude(),
        ];
    }

    /**
     * Read an array/relationship of ACARS model points
     */
    public function getFeatureFromAcars(Pirep $pirep): array
    {
        // Get the two airports
        $airports = new GeoJson();
        $airports->addPoint($pirep->dpt_airport->lat, $pirep->dpt_airport->lon, [
            'name' => $pirep->dpt_airport->name,
            'icao' => $pirep->dpt_airport->icao,
            'type' => 'D',
        ]);

        $airports->addPoint($pirep->arr_airport->lat, $pirep->arr_airport->lon, [
            'name' => $pirep->arr_airport->name,
            'icao' => $pirep->arr_airport->icao,
            'type' => 'A',
        ]);

        $route = new GeoJson();

        $actual_route = $this->acarsRepo->forPirep($pirep->id, AcarsType::FLIGHT_PATH);
        foreach ($actual_route as $point) {
            $route->addPoint($point->lat, $point->lon, [
                'pirep_id' => $pirep->id,
                'alt'      => $point->altitude,
                // 'popup'    => 'GS: '.$point->gs.'<br />Alt: '.$point->altitude,
            ]);
        }

        return [
            // If there is a position update from ACARS, show where it is
            // Otherwise, just assume it's at the arrival airport currently
            'position' => [
                'lat' => optional($pirep->position)->lat ?? $pirep->arr_airport->lat,
                'lon' => optional($pirep->position)->lon ?? $pirep->arr_airport->lon,
            ],
            'line'     => $route->getLine(),
            'points'   => $route->getPoints(),
            'airports' => [
                'a' => [
                    'icao' => $pirep->arr_airport->icao,
                    'lat'  => $pirep->arr_airport->lat,
                    'lon'  => $pirep->arr_airport->lon,
                ],
                'd' => [
                    'icao' => $pirep->dpt_airport->icao,
                    'lat'  => $pirep->dpt_airport->lat,
                    'lon'  => $pirep->dpt_airport->lon,
                ],
            ],
        ];
    }

    /**
     * Return a single feature point for the
     *
     * @param mixed $pireps
     */
    public function getFeatureForLiveFlights($pireps): \GeoJson\Feature\FeatureCollection
    {
        $flight = new GeoJson();

        /**
         * @var Pirep $pirep
         */
        foreach ($pireps as $pirep) {
            /**
             * @var $point \App\Models\Acars
             */
            $point = $pirep->position;
            if (!$point) {
                continue;
            }

            $flight->addPoint($point->lat, $point->lon, [
                'pirep_id' => $pirep->id,
                'alt'      => $point->altitude,
                'heading'  => $point->heading ?: 0,
            ]);
        }

        return $flight->getPoints();
    }

    /**
     * Return a FeatureCollection GeoJSON object
     */
    public function flightGeoJson(Flight $flight): array
    {
        $route = new GeoJson();

        // # Departure Airport
        $route->addPoint(optional($flight->dpt_airport)->lat, optional($flight->dpt_airport)->lon, [
            'name'  => $flight->dpt_airport_id,
            'popup' => optional($flight->dpt_airport)->full_name ?? $flight->dpt_airport_id,
            'icon'  => 'airport',
        ]);

        if ($flight->route) {
            $all_route_points = $this->getCoordsFromRoute(
                $flight->dpt_airport_id,
                $flight->arr_airport_id,
                [optional($flight->dpt_airport)->lat, optional($flight->dpt_airport)->lon],
                $flight->route
            );

            // lat, lon needs to be reversed for GeoJSON
            foreach ($all_route_points as $point) {
                $route->addPoint($point->lat, $point->lon, [
                    'name'  => $point->name,
                    'popup' => $point->name.' ('.$point->lat.', '.$point->lon.')',
                    'icon'  => '',
                ]);
            }
        }

        $route->addPoint(optional($flight->arr_airport)->lat, optional($flight->arr_airport)->lon, [
            'name'  => $flight->arr_airport_id,
            'popup' => optional($flight->arr_airport)->full_name ?? $flight->arr_airport_id,
            'icon'  => 'airport',
        ]);

        return [
            'route_points'       => $route->getPoints(),
            'planned_route_line' => $route->getLine(),
        ];
    }

    /**
     * Return a GeoJSON FeatureCollection for a PIREP
     */
    public function pirepGeoJson(Pirep $pirep): array
    {
        $planned = new GeoJson();
        $actual = new GeoJson();

        /*
         * PLANNED ROUTE
         */
        $planned->addPoint(optional($pirep->dpt_airport)->lat, optional($pirep->dpt_airport)->lon, [
            'name'  => $pirep->dpt_airport_id,
            'popup' => optional($pirep->dpt_airport)->full_name ?? $pirep->dpt_airport_id,
        ]);

        $planned_route = $this->acarsRepo->forPirep($pirep->id, AcarsType::ROUTE);
        foreach ($planned_route as $point) {
            $planned->addPoint($point->lat, $point->lon, [
                'name'  => $point->name,
                'popup' => $point->name.' ('.$point->lat.', '.$point->lon.')',
            ]);
        }

        $planned->addPoint(optional($pirep->arr_airport)->lat, optional($pirep->arr_airport)->lon, [
            'name'  => $pirep->arr_airport_id,
            'popup' => optional($pirep->arr_airport)->full_name ?? $pirep->arr_airport_id,
            'icon'  => 'airport',
        ]);

        /**
         * ACTUAL ROUTE
         */
        $actual_route = $this->acarsRepo->forPirep($pirep->id, AcarsType::FLIGHT_PATH);
        foreach ($actual_route as $point) {
            $actual->addPoint($point->lat, $point->lon, [
                'pirep_id' => $pirep->id,
                'name'     => $point->altitude,
                'popup'    => 'Spd: '.$point->gs.' kts<br />Alt: '.$point->altitude.' ft<br />Pos: '.$point->lat.', '.$point->lon,
            ]);
        }

        return [
            'planned_rte_points'  => $planned->getPoints(),
            'planned_rte_line'    => $planned->getLine(),
            'actual_route_points' => $actual->getPoints(),
            'actual_route_line'   => $actual->getLine(),
        ];
    }
}

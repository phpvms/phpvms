<?php

namespace App\Models;

use GeoJson\Feature\Feature;
use GeoJson\Feature\FeatureCollection;
use GeoJson\Geometry\LineString;
use GeoJson\Geometry\Point;

/**
 * Return different points/features in GeoJSON format
 * https://tools.ietf.org/html/rfc7946
 */
class GeoJson
{
    /**
     * @var int
     */
    protected $counter;

    /**
     * @var array [lon, lat] pairs
     */
    protected $line_coords = [];

    /**
     * @var Feature[]
     */
    protected $point_coords = [];

    /**
     * @param array $attrs Attributes of the Feature
     */
    public function addPoint($lat, $lon, array $attrs)
    {
        $point = [$lon, $lat];
        $this->line_coords[] = [$lon, $lat];

        if (array_key_exists('alt', $attrs)) {
            $point[] = (float) $attrs['alt'];
        }

        $this->point_coords[] = new Feature(new Point($point), $attrs);
        $this->counter++;
    }

    /**
     * Get the FeatureCollection for the line
     */
    public function getLine(): FeatureCollection
    {
        if (empty($this->line_coords) || \count($this->line_coords) < 2) {
            return new FeatureCollection([]);
        }

        return new FeatureCollection([
            new Feature(new LineString($this->line_coords)),
        ]);
    }

    /**
     * Get the feature collection of all the points
     */
    public function getPoints(): FeatureCollection
    {
        return new FeatureCollection($this->point_coords);
    }
}

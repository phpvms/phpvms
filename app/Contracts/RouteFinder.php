<?php

namespace App\Contracts;

/**
 * Abstract class for finding a route from a departure airport to an arrival airport
 * Can allow for multiple, configurable methods for finding a route, whether it reaches
 * out to a REST API or implement some sort of internal logic to look through a file
 * or whatever the logic might be.
 *
 * Just one public-facing method needs to be implemented
 */
abstract class RouteFinder
{
    /**
     * Find a route from the departure ICAO to the arrival ICAO.
     */
    abstract public function findRoute($dptIcao, $arrIcao): string;
}

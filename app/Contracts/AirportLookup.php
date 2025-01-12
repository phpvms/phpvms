<?php

namespace App\Contracts;

use App\Models\Airport;

abstract class AirportLookup
{
    /**
     * Lookup the information for an airport. Needs to return an instance of the
     * Airport model, or an array with the properties listed in the Airport model.
     *
     * The in-use implementation can be changed in the config/phpvms.php file, so
     * different services can be used, in-case vaCentral one isn't working or there
     * is a better one available. Don't handle any caching in this layer, that happens
     * at the service layer
     *
     * Return null if there's an error or nothing was found
     *
     * @example App\Services\AirportLookup\VaCentralLookup
     *
     * @param string $icao
     *
     * @return Airport|null
     */
    abstract public function getAirport($icao);
}

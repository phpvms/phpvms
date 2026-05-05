<?php

declare(strict_types=1);

namespace App\Services\AirportLookup;

use App\Contracts\AirportLookup;
use Illuminate\Support\Facades\Log;
use VaCentral\Contracts\IVaCentral;
use VaCentral\Exceptions\HttpException;
use VaCentral\Models\Airport;

class VaCentralLookup extends AirportLookup
{
    public function __construct(
        private readonly IVaCentral $client
    ) {}

    /**
     * Lookup the information for an airport
     *
     * @param string $icao
     */
    public function getAirport($icao): Airport|array
    {
        try {
            $airport = $this->client->getAirport($icao);
            // @phpstan-ignore-next-line
            $airport->location = $airport->city;
            // @phpstan-ignore-next-line
            $airport->timezone = $airport->tz;

            return $airport;
        } catch (HttpException $httpException) {
            Log::error($httpException->getMessage());

            return [];
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Metar;

use App\Contracts\Metar;
use App\Support\HttpClient;
use Exception;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Return the raw METAR/TAF string from the NOAA Aviation Weather Service
 */
class AviationWeather extends Metar
{
    private const string METAR_URL = 'https://aviationweather.gov/api/data/metar?ids=';

    private const string TAF_URL = 'https://aviationweather.gov/api/data/taf?ids=';

    public function __construct(
        private readonly HttpClient $httpClient
    ) {}

    /**
     * Implement the METAR - Return the string
     *
     *
     * @throws Exception
     * @throws GuzzleException
     */
    protected function get_metar($icao): string
    {
        if ($icao === '') {
            return '';
        }

        // A failed request throws: Metar::metar() logs it without caching it.
        return trim($this->httpClient->get(self::METAR_URL.$icao));
    }

    /**
     * Do the actual retrieval of the TAF
     *
     *
     * @throws GuzzleException
     */
    protected function get_taf($icao): string
    {
        if ($icao === '') {
            return '';
        }

        // Remove " \n" to remove new lines from the metar content. A failed
        // request throws: Metar::taf() logs it without caching it.
        return trim(str_replace(" \n", '', $this->httpClient->get(self::TAF_URL.$icao)));
    }
}

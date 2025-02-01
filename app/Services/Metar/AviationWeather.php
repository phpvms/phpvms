<?php

namespace App\Services\Metar;

use App\Contracts\Metar;
use App\Support\HttpClient;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Return the raw METAR/TAF string from the NOAA Aviation Weather Service
 */
class AviationWeather extends Metar
{
    private const METAR_URL = 'https://aviationweather.gov/api/data/metar?ids=';

    private const TAF_URL = 'https://aviationweather.gov/api/data/taf?ids=';

    public function __construct(
        private readonly HttpClient $httpClient
    ) {}

    /**
     * Implement the METAR - Return the string
     *
     *
     * @throws \Exception
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function get_metar($icao): string
    {
        if ($icao === '') {
            return '';
        }

        $url = self::METAR_URL.$icao;

        try {
            $raw_metar = $this->httpClient->get($url);

            return trim($raw_metar);
        } catch (Exception $e) {
            Log::error('Error reading METAR: '.$e->getMessage());

            return '';
        }
    }

    /**
     * Do the actual retrieval of the TAF
     *
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function get_taf($icao): string
    {
        if ($icao === '') {
            return '';
        }

        $url = self::TAF_URL.$icao;

        try {
            $raw_taf = $this->httpClient->get($url);

            // Remove " \n" to remove new lines from the metar content
            return trim(str_replace(" \n", '', $raw_taf));
        } catch (Exception $e) {
            Log::error('Error reading TAF: '.$e->getMessage());

            return '';
        }
    }
}

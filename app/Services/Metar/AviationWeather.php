<?php

namespace App\Services\Metar;

use App\Contracts\Metar;
use App\Support\HttpClient;
use Exception;
use Illuminate\Support\Facades\Log;

use function count;

/**
 * Return the raw METAR/TAF string from the NOAA Aviation Weather Service
 */
class AviationWeather extends Metar
{
    private const METAR_URL = 'https://aviationweather.gov/cgi-bin/data/dataserver.php?requestType=retrieve&format=xml&hoursBeforeNow=3&mostRecent=true&dataSource=metars&stationString=';

    private const TAF_URL = 'https://aviationweather.gov/cgi-bin/data/dataserver.php?requestType=retrieve&format=xml&hoursBeforeNow=3&mostRecent=true&dataSource=tafs&stationString=';

    /**
     * @param HttpClient $httpClient
     */
    public function __construct(
        private readonly HttpClient $httpClient
    ) {
    }

    /**
     * Implement the METAR - Return the string
     *
     * @param $icao
     *
     * @throws \Exception
     * @throws \GuzzleHttp\Exception\GuzzleException
     *
     * @return string
     */
    protected function get_metar($icao): string
    {
        if ($icao === '') {
            return '';
        }

        $url = static::METAR_URL.$icao;

        try {
            $res = $this->httpClient->get($url, []);
            $xml = simplexml_load_string($res);

            if ($xml->errors && count($xml->errors->children()) > 0) {
                return '';
            }

            $attrs = $xml->data->attributes();
            if (!isset($attrs['num_results'])) {
                return '';
            }

            $num_results = $attrs['num_results'];
            if (empty($num_results)) {
                return '';
            }

            $num_results = (int) $num_results;
            if ($num_results === 0) {
                return '';
            }

            if (count($xml->data->METAR->raw_text) === 0) {
                return '';
            }

            return $xml->data->METAR->raw_text->__toString();
        } catch (Exception $e) {
            Log::error('Error reading METAR: '.$e->getMessage());
            return '';
        }
    }

    /**
     * Do the actual retrieval of the TAF
     *
     * @param $icao
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     *
     * @return string
     */
    protected function get_taf($icao): string
    {
        if ($icao === '') {
            return '';
        }

        $tafurl = static::TAF_URL.$icao;

        try {
            $tafres = $this->httpClient->get($tafurl, []);
            $tafxml = simplexml_load_string($tafres);

            $tafattrs = $tafxml->data->attributes();
            if (!isset($tafattrs['num_results'])) {
                return '';
            }

            $tafnum_results = $tafattrs['num_results'];
            if (empty($tafnum_results)) {
                return '';
            }

            $tafnum_results = (int) $tafnum_results;
            if ($tafnum_results === 0) {
                return '';
            }

            if (count($tafxml->data->TAF->raw_text) === 0) {
                return '';
            }

            return $tafxml->data->TAF->raw_text->__toString();
        } catch (Exception $e) {
            Log::error('Error reading TAF: '.$e->getMessage());
            return '';
        }
    }
}

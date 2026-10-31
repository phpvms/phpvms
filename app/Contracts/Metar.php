<?php

namespace App\Contracts;

use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Base class for implementing retrieving METARs
 */
abstract class Metar
{
    /**
     * Implement retrieving the METAR - return the METAR string. Needs to be protected,
     * since this shouldn't be directly called. Call `metar($icao)`. If not implemented,
     * return a blank string. Throw when the request fails, so the failure isn't cached
     * as the station having no report.
     */
    abstract protected function get_metar($icao): string;

    /**
     * Implement retrieving the TAF - return the string. Call `taf($icao)`. If not implemented,
     * return a blank string
     */
    abstract protected function get_taf($icao): string;

    /**
     * Download the METAR, wrap in caching
     */
    public function metar(string $icao): string
    {
        $cache = config('cache.keys.METAR_WEATHER_LOOKUP');
        $key = $cache['key'].$icao;

        // '' is a cached "no report": the station has none, so don't ask on every page.
        $raw_metar = Cache::get($key);
        if (is_string($raw_metar)) {
            return $raw_metar;
        }

        try {
            $raw_metar = $this->get_metar($icao);
        } catch (Exception $exception) {
            // Not cached: the report shows again as soon as the provider answers.
            Log::error('Error getting METAR: '.$exception->getMessage(), $exception->getTrace());

            return '';
        }

        Cache::put($key, $raw_metar, $raw_metar === '' ? ($cache['missing_time'] ?? 15 * 60) : $cache['time']);

        return $raw_metar;
    }

    /**
     * Download the TAF, wrap in caching
     */
    public function taf(string $icao): string
    {
        $cache = config('cache.keys.TAF_WEATHER_LOOKUP');
        $key = $cache['key'].$icao;

        // '' is a cached "no report": the station has none, so don't ask on every page.
        $taf = Cache::get($key);
        if (is_string($taf)) {
            return $taf;
        }

        try {
            $taf = $this->get_taf($icao);
        } catch (Exception $exception) {
            // Not cached: the report shows again as soon as the provider answers.
            Log::error('Error getting TAF: '.$exception->getMessage(), $exception->getTrace());

            return '';
        }

        Cache::put($key, $taf, $taf === '' ? ($cache['missing_time'] ?? 15 * 60) : $cache['time']);

        return $taf;
    }
}

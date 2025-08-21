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
     * return a blank string
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
    public function metar($icao): string
    {
        $cache = config('cache.keys.METAR_WEATHER_LOOKUP');
        $key = $cache['key'].$icao;

        if (Cache::has($key)) {
            $raw_metar = Cache::get($key);
            if ($raw_metar !== '') {
                return $raw_metar;
            }
        }

        try {
            $raw_metar = $this->get_metar($icao);
        } catch (Exception $e) {
            Log::error('Error getting METAR: '.$e->getMessage(), $e->getTrace());

            return '';
        }

        if ($raw_metar !== '') {
            Cache::put($key, $raw_metar, $cache['time']);
        }

        return $raw_metar;
    }

    /**
     * Download the TAF, wrap in caching
     */
    public function taf($icao): string
    {
        $cache = config('cache.keys.TAF_WEATHER_LOOKUP');
        $key = $cache['key'].$icao;

        if (Cache::has($key)) {
            $taf = Cache::get($key);
            if ($taf !== '') {
                return $taf;
            }
        }

        try {
            $taf = $this->get_taf($icao);
        } catch (Exception $e) {
            Log::error('Error getting TAF: '.$e->getMessage(), $e->getTrace());

            return '';
        }

        if ($taf !== '') {
            Cache::put($key, $taf, $cache['time']);
        }

        return $taf;
    }
}

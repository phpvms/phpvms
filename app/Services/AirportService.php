<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Metar as MetarProvider;
use App\Contracts\Service;
use App\Exceptions\AirportNotFound;
use App\Models\Airport;
use App\Support\Dto\PhpvmsApi\AirportData;
use App\Support\Metar;
use App\Support\Units\Distance;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use League\Geotools\Coordinate\Coordinate;
use League\Geotools\Geotools;
use PhpUnitsOfMeasure\Exception\NonNumericValue;
use PhpUnitsOfMeasure\Exception\NonStringUnitName;

class AirportService extends Service
{
    public function __construct(
        private readonly MetarProvider $metarProvider
    ) {}

    /**
     * Return the METAR for a given airport
     */
    public function getMetar(?string $icao): ?Metar
    {
        if ($icao === null) {
            return null;
        }

        $icao = trim($icao);
        if ($icao === '') {
            return null;
        }

        $raw_metar = $this->metarProvider->metar($icao);
        if ($raw_metar !== '' && $raw_metar !== '0') {
            return new Metar($raw_metar);
        }

        return null;
    }

    /**
     * Return the METAR for a given airport
     */
    public function getTaf(?string $icao): ?Metar
    {
        if ($icao === null) {
            return null;
        }

        $icao = trim($icao);
        if ($icao === '') {
            return null;
        }

        $raw_taf = $this->metarProvider->taf($icao);
        if ($raw_taf !== '' && $raw_taf !== '0') {
            return new Metar($raw_taf, true);
        }

        return null;
    }

    /**
     * Lookup an airport's information from a remote provider. This handles caching
     * the data internally
     */
    public function lookupAirport(string $icao): array
    {
        $key = config('cache.keys.AIRPORT_VACENTRAL_LOOKUP.key').$icao;

        $airport = Cache::get($key);
        if ($airport) {
            return $airport;
        }

        try {
            $response = Http::connectTimeout(2)
                ->timeout(5)
                ->get(config('phpvms.api_url').'/v1/airports/'.$icao);
        } catch (ConnectionException) {
            return [];
        }

        if (!$response->successful()) {
            return [];
        }

        $airportData = AirportData::from($response->json());

        $airport = $airportData->toArray();

        Cache::add(
            $key,
            $airport,
            config('cache.keys.AIRPORT_VACENTRAL_LOOKUP.time')
        );

        return $airport;
    }

    /**
     * Search for airports from vACentral.
     *
     * @return list<array{
     *     icao: string,
     *     iata: string,
     *     name: string,
     *     location: string,
     *     country: string,
     *     region: string,
     *     timezone: string,
     *     elevation: int,
     *     lat: float,
     *     lon: float
     * }>
     */
    public function searchAirports(string $search): array
    {
        try {
            $response = Http::acceptJson()
                ->connectTimeout(2)
                ->timeout(5)
                ->get(config('phpvms.api_url').'/v2/airports/search', [
                    'text' => $search,
                ]);
        } catch (ConnectionException) {
            return [];
        }

        if (!$response->successful()) {
            return [];
        }

        return collect($response->json())
            ->filter(fn (mixed $airport): bool => is_array($airport)
                && filled($airport['icao'] ?? null)
                && filled($airport['name'] ?? null))
            ->map(fn (array $airport): array => [
                'icao'      => strtoupper((string) $airport['icao']),
                'iata'      => strtoupper((string) ($airport['iata'] ?? '')),
                'name'      => (string) $airport['name'],
                'location'  => (string) ($airport['city'] ?? ''),
                'country'   => strtoupper((string) ($airport['country'] ?? '')),
                'region'    => strtoupper((string) ($airport['region'] ?? '')),
                'timezone'  => (string) ($airport['tz'] ?? ''),
                'elevation' => (int) ($airport['alt'] ?? 0),
                'lat'       => (float) ($airport['lat'] ?? 0),
                'lon'       => (float) ($airport['lon'] ?? 0),
            ])
            ->values()
            ->all();
    }

    public function addAirportFromVacentral(string $icao): ?Airport
    {
        $icao = strtoupper($icao);
        $airport = Airport::find($icao);

        if ($airport !== null) {
            return $airport;
        }

        $lookup = $this->lookupAirport($icao);

        if ($lookup === []) {
            return null;
        }

        $airport = new Airport($lookup);
        $airport->save();

        return $airport;
    }

    /**
     * Lookup an airport and save it if it hasn't been found
     *
     * @param string $icao
     */
    public function lookupAirportIfNotFound($icao): ?Airport
    {
        $icao = strtoupper($icao);
        $airport = Airport::find($icao);
        if ($airport !== null) {
            return $airport;
        }

        // Don't lookup the airport, so just add in something generic
        if (!setting('general.auto_airport_lookup')) {
            $airport = new Airport([
                'id'   => $icao,
                'icao' => $icao,
                'name' => $icao,
                'lat'  => 0,
                'lon'  => 0,
            ]);

            $airport->save();

            return $airport;
        }

        return $this->addAirportFromVacentral($icao);
    }

    /**
     * Calculate the distance from one airport to another
     */
    public function calculateDistance(string $fromIcao, string $toIcao): ?Distance
    {
        $from = Airport::find($fromIcao, ['lat', 'lon']);
        $to = Airport::find($toIcao, ['lat', 'lon']);

        if (!$from) {
            throw new AirportNotFound($fromIcao);
        }

        if (!$to) {
            throw new AirportNotFound($toIcao);
        }

        // Calculate the distance
        $geotools = new Geotools();
        $start = new Coordinate([$from->lat, $from->lon]);
        $end = new Coordinate([$to->lat, $to->lon]);
        /** @var \League\Geotools\Distance\Distance $dist */
        $dist = $geotools->distance()->setFrom($start)->setTo($end)->in('mi');

        // Convert into a Distance object
        try {
            return new Distance($dist->greatCircle(), 'mi');
        } catch (NonNumericValue|NonStringUnitName) {
            return null;
        }
    }
}

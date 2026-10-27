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
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use League\Geotools\Coordinate\Coordinate;
use League\Geotools\Geotools;
use PhpUnitsOfMeasure\Exception\NonNumericValue;
use PhpUnitsOfMeasure\Exception\NonStringUnitName;

class AirportService extends Service
{
    /**
     * Every column that stores an airport id, by table. The same set
     * 2026_10_22_225212_update_airport_id_fields_size widens.
     *
     * @var array<string, list<string>>
     */
    private const array AIRPORT_ID_COLUMNS = [
        'aircraft'  => ['airport_id', 'hub_id'],
        'flights'   => ['dpt_airport_id', 'arr_airport_id', 'alt_airport_id'],
        'pireps'    => ['dpt_airport_id', 'arr_airport_id', 'alt_airport_id'],
        'subfleets' => ['hub_id'],
        'users'     => ['home_airport_id', 'curr_airport_id'],
    ];

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

    /**
     * Resolve a value that names an airport, by id first and then by ICAO.
     *
     * Callers hand this either an id or an ICAO and cannot always tell which:
     * a flight row's `dpt_airport_id` holds whichever one was typed into it.
     * The ICAO fallback is what keeps a caller that names `KJFK` from creating
     * a second airport when the catalog already stored that one under a
     * generated id. Compared through `UPPER()` rather than on the column so
     * the match does not depend on the connection's collation.
     *
     * Public because the paths that only want to *validate* that an airport is
     * already here -- `PirepService::prefile()` with
     * `general.allow_unadded_airports` off -- need the same id-or-ICAO
     * resolution as the paths that are allowed to add one.
     */
    public function findAirport(string $value): ?Airport
    {
        return Airport::find($value)
            ?? Airport::whereRaw('UPPER(icao) = ?', [strtoupper($value)])->first();
    }

    public function addAirportFromVacentral(string $icao): ?Airport
    {
        $icao = strtoupper(trim($icao));
        $airport = $this->findAirport($icao);

        if ($airport instanceof Airport) {
            return $airport;
        }

        $lookup = $this->lookupAirport($icao);

        if ($lookup === []) {
            return null;
        }

        // `$lookup` carries the catalog's own id, so the row lands under that
        // rather than under a second copy of its ICAO. A response without one
        // leaves `id` null and `AirportObserver` fills it from the ICAO.
        $airport = new Airport($lookup);
        $airport->save();

        return $airport;
    }

    /**
     * Airport ids that other rows reference but no airport row carries.
     *
     * A soft-deleted airport still counts as present: its row exists, and
     * restoring it is a different job from creating it.
     *
     * @return array<string, int> airport id => number of references, most
     *                            referenced first
     */
    public function findMissingAirportIds(): array
    {
        $references = null;

        foreach (self::AIRPORT_ID_COLUMNS as $table => $columns) {
            foreach ($columns as $column) {
                $query = DB::table($table)
                    ->select($column.' as airport_id')
                    ->whereNotNull($column)
                    ->where($column, '<>', '');

                $references = $references?->unionAll($query) ?? $query;
            }
        }

        return DB::query()
            ->fromSub($references, 'refs')
            ->select('refs.airport_id')
            ->selectRaw('count(*) as references_count')
            ->whereNotExists(fn (Builder $query) => $query
                ->selectRaw('1')
                ->from('airports')
                ->whereColumn('airports.id', 'refs.airport_id'))
            ->groupBy('refs.airport_id')
            ->orderByDesc('references_count')
            ->orderBy('refs.airport_id')
            ->pluck('references_count', 'airport_id')
            ->map(fn (mixed $count): int => (int) $count)
            ->all();
    }

    /**
     * Create the airport a missing reference names, from the remote lookup.
     *
     * The referencing columns hold ICAO codes, so the value is looked up as
     * an ICAO -- the lookup endpoint is keyed by ICAO, not by catalog id.
     * Unlike addAirportFromVacentral(), the row keeps the value it is missing
     * under rather than the catalog's own id: the point is to give the rows
     * that already reference it an airport to resolve to.
     */
    public function addMissingAirport(string $airportId): ?Airport
    {
        if (Airport::withTrashed()->whereKey($airportId)->exists()) {
            return null;
        }

        $lookup = $this->lookupAirport(strtoupper(trim($airportId)));

        if ($lookup === []) {
            return null;
        }

        $airport = new Airport([...Arr::except($lookup, ['id']), 'id' => $airportId]);
        $airport->save();

        return $airport;
    }

    /**
     * Lookup an airport and save it if it hasn't been found.
     *
     * Returns the airport the value actually resolves to, which is not always
     * the value passed in: a lookup stores the catalog's id, so a caller that
     * named an ICAO has to read the id back off the returned airport before it
     * stores a reference to it.
     *
     * @param string $icao
     */
    public function lookupAirportIfNotFound($icao): ?Airport
    {
        // Trimmed, not folded. The value may be an id, and an id is matched as
        // it was stored; `findAirport()` handles the case of an ICAO itself.
        $value = trim((string) $icao);

        $airport = $this->findAirport($value);
        if ($airport instanceof Airport) {
            return $airport;
        }

        // Don't lookup the airport, so just add in something generic
        if (!setting('general.auto_airport_lookup')) {
            // The generic row names itself after the value, and `icao` is
            // varchar(8) while an id now holds 16. A value too wide to be an
            // ICAO is a generated id we know nothing about, and there is no
            // honest ICAO to put beside it -- so refuse rather than hand the
            // database a value it has to reject or truncate. Callers already
            // handle the null this method has always been able to return.
            if (strlen($value) > 8) {
                return null;
            }

            $airport = new Airport([
                'id'   => $value,
                'icao' => $value,
                'name' => $value,
                'lat'  => 0,
                'lon'  => 0,
            ]);

            $airport->save();

            return $airport;
        }

        return $this->addAirportFromVacentral($value);
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

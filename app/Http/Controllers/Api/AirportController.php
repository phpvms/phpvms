<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Controller;
use App\Http\Requests\SearchAirportsRequest;
use App\Http\Resources\Airport as AirportResource;
use App\Http\Resources\AirportDistance as AirportDistanceResource;
use App\Models\Airport;
use App\Queries\AirportSearchQueryV1;
use App\Services\AirportService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Class AirportController
 */
class AirportController extends Controller
{
    public function __construct(
        private readonly AirportService $airportSvc
    ) {}

    /**
     * Return all the airports, paginated.
     */
    public function index(SearchAirportsRequest $request): AnonymousResourceCollection
    {
        $airports = (new AirportSearchQueryV1($request))
            ->build()
            ->paginate($this->perPage($request))
            ->appends($request->except(['page', 'user']));

        return AirportResource::collection($airports);
    }

    public function index_hubs(SearchAirportsRequest $request): AnonymousResourceCollection
    {
        $airports = Airport::byHub()
            ->orderByIcao()
            ->paginate($this->perPage($request))
            ->appends($request->except(['page', 'user']));

        return AirportResource::collection($airports);
    }

    /**
     * Return a specific airport. Uses route model binding via
     * Airport::resolveRouteBinding() (case-insensitive ICAO).
     */
    public function get(Airport $airport): AirportResource
    {
        return new AirportResource($airport);
    }

    /**
     * Do a lookup, via vaCentral, for the airport information.
     */
    public function lookup(string $id): AirportResource
    {
        $airport = $this->airportSvc->lookupAirport($id);

        return new AirportResource(collect($airport));
    }

    /**
     * Return the distance between two airports.
     */
    public function distance(string $fromIcao, string $toIcao): AirportDistanceResource
    {
        $distance = $this->airportSvc->calculateDistance($fromIcao, $toIcao);

        return new AirportDistanceResource([
            'fromIcao' => $fromIcao,
            'toIcao'   => $toIcao,
            'distance' => $distance,
        ]);
    }

    /**
     * Search for airports in the database.
     */
    public function search(SearchAirportsRequest $request): AnonymousResourceCollection
    {
        $airports = (new AirportSearchQueryV1($request))
            ->build()
            ->paginate($this->perPage($request), ['id', 'iata', 'icao', 'name', 'hub'])
            ->appends($request->except(['page', 'user']));

        return AirportResource::collection($airports);
    }

    /**
     * Resolve the `?limit=` query param consistently with the (now removed)
     * Prettus contract's paginate() override at app/Contracts/Repository.php:112-129.
     * Phase 2 PR (#2192) established this pattern.
     */
    private function perPage(Request $request): int
    {
        return paginate_limit($request->integer('limit') ?: null);
    }
}

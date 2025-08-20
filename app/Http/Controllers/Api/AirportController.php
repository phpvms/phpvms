<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Controller;
use App\Http\Resources\Airport as AirportResource;
use App\Http\Resources\AirportDistance as AirportDistanceResource;
use App\Repositories\AirportRepository;
use App\Repositories\Criteria\WhereCriteria;
use App\Services\AirportService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Prettus\Repository\Criteria\RequestCriteria;

/**
 * Class AirportController
 */
class AirportController extends Controller
{
    /**
     * AirportController constructor.
     */
    public function __construct(
        private readonly AirportRepository $airportRepo,
        private readonly AirportService $airportSvc
    ) {}

    /**
     * Return all the airports, paginated
     *
     *
     * @return mixed
     */
    public function index(Request $request)
    {
        $where = [];
        if ($request->filled('hub')) {
            $where['hub'] = $request->get('hub');
        }

        $this->airportRepo->pushCriteria(new RequestCriteria($request));

        $airports = $this->airportRepo
            ->whereOrder($where, 'icao', 'asc')
            ->paginate();

        return AirportResource::collection($airports);
    }

    public function index_hubs(): AnonymousResourceCollection
    {
        $where = [
            'hub' => true,
        ];

        $airports = $this->airportRepo
            ->whereOrder($where, 'icao', 'asc')
            ->paginate();

        return AirportResource::collection($airports);
    }

    /**
     * Return a specific airport
     */
    public function get(string $id): AirportResource
    {
        $id = strtoupper($id);

        return new AirportResource($this->airportRepo->find($id));
    }

    /**
     * Do a lookup, via vaCentral, for the airport information
     */
    public function lookup(string $id): AirportResource
    {
        $airport = $this->airportSvc->lookupAirport($id);

        return new AirportResource(collect($airport));
    }

    /**
     * Do a lookup, via vaCentral, for the airport information
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
     * Search for airports in the database
     */
    public function search(Request $request): AnonymousResourceCollection
    {
        $this->airportRepo->resetCriteria();
        $this->airportRepo->pushCriteria(app(RequestCriteria::class));

        // Restrict search to hubs only?
        if (get_truth_state($request->get('hubs', false)) === true) {
            $this->airportRepo->pushCriteria(new WhereCriteria($request, ['hub' => true]));
        }

        $airports = $this->airportRepo->paginate(null, ['id', 'iata', 'icao', 'name', 'hub']);

        return AirportResource::collection($airports);
    }
}

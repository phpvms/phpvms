<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Controller;
use App\Http\Resources\Aircraft as AircraftResource;
use App\Http\Resources\Subfleet as SubfleetResource;
use App\Repositories\AircraftRepository;
use App\Repositories\SubfleetRepository;
use Illuminate\Http\Request;

/**
 * Class FleetController
 */
class FleetController extends Controller
{
    /**
     * FleetController constructor.
     */
    public function __construct(
        private readonly AircraftRepository $aircraftRepo,
        private readonly SubfleetRepository $subfleetRepo
    ) {}

    /**
     * Return all the subfleets and the aircraft and any other associated data
     * Paginated
     */
    public function index()
    {
        $subfleets = $this->subfleetRepo
            ->with(['aircraft', 'airline', 'fares', 'ranks'])
            ->paginate();

        return SubfleetResource::collection($subfleets);
    }

    /**
     * Get a specific aircraft. Query string required to specify the tail
     * /api/aircraft/XYZ?type=registration
     */
    public function get_aircraft(string $id, Request $request): AircraftResource
    {
        $type = $request->get('type', 'id');
        if (!in_array($type, ['id', 'registration', 'icao', 'iata', 'fin', 'hex_code'], true)) {
            $type = 'id';
        }

        $where = [$type => $id];

        $aircraft = $this->aircraftRepo
            ->with('subfleet.fares')
            ->findWhere($where)
            ->first();

        return new AircraftResource($aircraft);
    }
}

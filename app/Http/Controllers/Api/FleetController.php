<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Controller;
use App\Http\Resources\AircraftResource;
use App\Http\Resources\SubfleetResource;
use App\Models\Aircraft;
use App\Models\Subfleet;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Class FleetController
 */
class FleetController extends Controller
{
    /**
     * Return all the subfleets and the aircraft and any other associated data
     * Paginated
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $limit = paginate_limit($request->integer('limit') ?: null);

        $subfleets = Subfleet::with(['aircraft', 'airline', 'fares', 'ranks'])
            ->paginate($limit)
            ->appends($request->except(['page', 'user']));

        return SubfleetResource::collection($subfleets);
    }

    /**
     * Get a specific aircraft. Query string required to specify the tail
     * /api/aircraft/XYZ?type=registration
     */
    public function get_aircraft(string $id, Request $request): AircraftResource
    {
        $where = [];
        if ($request->filled('type')) {
            $where[$request->input('type')] = $id;
        } else {
            $where['id'] = $id;
        }

        $aircraft = Aircraft::with('subfleet.fares')
            ->where($where)
            ->first();

        return new AircraftResource($aircraft);
    }
}

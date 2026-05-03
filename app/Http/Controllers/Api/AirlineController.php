<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Controller;
use App\Http\Resources\AirlineResource;
use App\Models\Airline;
use Illuminate\Http\Request;

class AirlineController extends Controller
{
    /**
     * Return all the airlines, paginated
     *
     * @return mixed
     */
    public function index(Request $request)
    {
        $airlines = Airline::active()->orderBy('name')->paginate();

        return AirlineResource::collection($airlines);
    }

    /**
     * Return a specific airline
     */
    public function get(int $id): AirlineResource
    {
        return new AirlineResource(Airline::findOrFail($id));
    }
}

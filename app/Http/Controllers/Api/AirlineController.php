<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Controller;
use App\Http\Resources\Airline as AirlineResource;
use App\Repositories\AirlineRepository;
use Illuminate\Http\Request;

class AirlineController extends Controller
{
    /**
     * AirlineController constructor.
     */
    public function __construct(
        private readonly AirlineRepository $airlineRepo
    ) {}

    /**
     * Return all the airlines, paginated.
     *
     *
     * @return mixed
     */
    public function index(Request $request)
    {
        $airlines = $this->airlineRepo->whereOrder(['active' => true], 'name')->paginate();

        return AirlineResource::collection($airlines);
    }

    /**
     * Return a specific airline.
     */
    public function get(int $id): AirlineResource
    {
        return new AirlineResource($this->airlineRepo->find($id));
    }
}

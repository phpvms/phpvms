<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Controller;
use App\Exceptions\AssetNotFound;
use App\Http\Requests\SearchFlightsRequest;
use App\Http\Resources\Flight as FlightResource;
use App\Http\Resources\Navdata as NavdataResource;
use App\Models\Aircraft;
use App\Models\Enums\AircraftState;
use App\Models\Enums\AircraftStatus;
use App\Models\Flight;
use App\Models\SimBrief;
use App\Models\User;
use App\Queries\FlightSearchQuery;
use App\Services\FareService;
use App\Services\FlightService;
use App\Services\UserService;
use Exception;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class FlightController extends Controller
{
    public function __construct(
        private readonly FareService $fareSvc,
        private readonly FlightSearchQuery $flightSearchQuery,
        private readonly FlightService $flightSvc,
        private readonly UserService $userSvc
    ) {}

    /**
     * Return all the flights, paginated
     */
    public function index(SearchFlightsRequest $request): AnonymousResourceCollection
    {
        return $this->search($request);
    }

    public function get(string $id): FlightResource
    {
        /** @var User $user */
        $user = Auth::user();

        /** @var Flight $flight */
        $flight = Flight::with([
            'airline',
            'fares',
            'subfleets' => ['aircraft.bid', 'fares'],
            'field_values',
            'simbrief' => function ($query) use ($user) {
                return $query->with('aircraft')->where('user_id', $user->id);
            },
        ])->findOrFail($id);

        $flight = $this->flightSvc->filterSubfleets($user, $flight);
        $flight = $this->fareSvc->getReconciledFaresForFlight($flight);

        return new FlightResource($flight);
    }

    public function search(SearchFlightsRequest $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = Auth::user();

        $query = $this->flightSearchQuery->build($request)
            ->whereHas('airline', function ($q) {
                $q->where('active', true);
            });

        // Allow the option to bypass some of these restrictions for the searches
        if (!$request->filled('ignore_restrictions') || $request->input('ignore_restrictions') === '0') {
            if (setting('pilots.restrict_to_company')) {
                $query->where('airline_id', $user->airline_id);
            }

            if (setting('pilots.only_flights_from_current')) {
                $query->where('dpt_airport_id', $user->curr_airport_id);
            }
        }

        $filterByUser = setting('pireps.restrict_aircraft_to_rank', false) || setting('pireps.restrict_aircraft_to_typerating', false);
        if ($filterByUser) {
            $query->whereIn('id', $this->flightSvc->getAccessibleFlightIds($user));
        }

        $with = [
            'airline',
            'fares',
            'field_values',
            'simbrief' => function ($q) use ($user) {
                return $q->with('aircraft')->where('user_id', $user->id);
            },
        ];

        $relations = ['subfleets'];
        if ($request->has('with')) {
            $relations = explode(',', $request->input('with', ''));
        }

        foreach ($relations as $relation) {
            $with = array_merge($with, match ($relation) {
                'subfleets' => [
                    'subfleets',
                    'subfleets.aircraft',
                    'subfleets.aircraft.bid',
                    'subfleets.fares',
                ],
                default => [],
            });
        }

        $perPage = $request->integer('limit') ?: null;
        $flights = $query->with($with)->paginate($perPage);

        foreach ($flights as $flight) {
            if (in_array('subfleets', $relations)) {
                $this->flightSvc->filterSubfleets($user, $flight);
            }

            $this->fareSvc->getReconciledFaresForFlight($flight);
        }

        return FlightResource::collection($flights);
    }

    /**
     * Output the flight briefing from simbrief or whatever other format
     *
     * @param  string                   $id The flight ID
     * @return ResponseFactory|Response
     */
    public function briefing(string $id)
    {
        /** @var ?SimBrief $simbrief */
        $simbrief = SimBrief::where('flight_id', $id)->first();

        if ($simbrief === null) {
            throw new AssetNotFound(new Exception('Flight briefing not found'));
        }

        if (!$simbrief->ofp_json_path || !Storage::exists($simbrief->ofp_json_path)) {
            throw new AssetNotFound(new Exception('Flight briefing not found'));
        }

        $json = Storage::get($simbrief->ofp_json_path);

        return response($json, 200, [
            'Content-Type' => 'application/json',
        ]);
    }

    /**
     * Get a flight's route
     */
    public function route(string $id, Request $request): AnonymousResourceCollection
    {
        $flight = Flight::findOrFail($id);
        $route = $this->flightSvc->getRoute($flight);

        return NavdataResource::collection($route);
    }

    /**
     * Get a flight's aircrafts
     */
    public function aircraft(string $id, Request $request)
    {
        /** @var Flight $flight */
        $flight = Flight::with('subfleets')->findOrFail($id);

        $user_subfleets = $this->userSvc->getAllowableSubfleets(Auth::user())->pluck('id')->toArray();
        $flight_subfleets = $flight->subfleets->pluck('id')->toArray();

        $subfleet_ids = filled($flight_subfleets) ? array_intersect($user_subfleets, $flight_subfleets) : $user_subfleets;

        // Prepare variables for single aircraft query
        $where = [];
        $where['state'] = AircraftState::PARKED;
        $where['status'] = AircraftStatus::ACTIVE;

        if (setting('pireps.only_aircraft_at_dpt_airport')) {
            $where['airport_id'] = $flight->dpt_airport_id;
        }

        $withCount = ['bid', 'simbriefs' => function ($query) {
            $query->whereNull('pirep_id');
        }];

        // Build proper aircraft collection considering all possible settings
        // Flight subfleets, user subfleet restrictions, pirep restrictions, simbrief blocking etc
        $aircraft = Aircraft::withCount($withCount)->where($where)
            ->when(setting('simbrief.block_aircraft'), function ($query) {
                return $query->having('simbriefs_count', 0);
            })->when(setting('bids.block_aircraft'), function ($query) {
                return $query->having('bid_count', 0);
            })->whereIn('subfleet_id', $subfleet_ids)
            ->orderby('icao')->orderby('registration')
            ->get();

        return $aircraft;
    }
}

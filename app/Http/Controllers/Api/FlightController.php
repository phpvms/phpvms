<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Controller;
use App\Exceptions\AssetNotFound;
use App\Http\Resources\Flight as FlightResource;
use App\Http\Resources\Navdata as NavdataResource;
use App\Models\Aircraft;
use App\Models\Enums\AircraftState;
use App\Models\Enums\AircraftStatus;
use App\Models\SimBrief;
use App\Models\User;
use App\Repositories\Criteria\WhereCriteria;
use App\Repositories\FlightRepository;
use App\Services\FareService;
use App\Services\FlightService;
use App\Services\UserService;
use Exception;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Prettus\Repository\Criteria\RequestCriteria;
use Prettus\Repository\Exceptions\RepositoryException;

class FlightController extends Controller
{
    public function __construct(
        private readonly FareService $fareSvc,
        private readonly FlightRepository $flightRepo,
        private readonly FlightService $flightSvc,
        private readonly UserService $userSvc
    ) {}

    /**
     * Return all the flights, paginated
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        return $this->search($request);
    }

    public function get(string $id): FlightResource
    {
        /** @var User $user */
        $user = Auth::user();

        /** @var \App\Models\Flight $flight */
        $flight = $this->flightRepo->with([
            'airline',
            'fares',
            'subfleets' => ['aircraft.bid', 'fares'],
            'field_values',
            'simbrief' => function ($query) use ($user) {
                return $query->with('aircraft')->where('user_id', $user->id);
            },
        ])->find($id);

        $flight = $this->flightSvc->filterSubfleets($user, $flight);
        $flight = $this->fareSvc->getReconciledFaresForFlight($flight);

        return new FlightResource($flight);
    }

    /**
     * @return mixed
     */
    public function search(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        $where = [
            'active'  => true,
            'visible' => true,
        ];

        // Allow the option to bypass some of these restrictions for the searches
        if (!$request->filled('ignore_restrictions') || $request->input('ignore_restrictions') === '0') {
            if (setting('pilots.restrict_to_company')) {
                $where['airline_id'] = $user->airline_id;
            }

            if (setting('pilots.only_flights_from_current')) {
                $where['dpt_airport_id'] = $user->curr_airport_id;
            }
        }

        try {
            $this->flightRepo->resetCriteria();
            $this->flightRepo->searchCriteria($request);
            $this->flightRepo->pushCriteria(new WhereCriteria($request, $where, [
                'airline' => ['active' => true],
            ]));

            $this->flightRepo->pushCriteria(new RequestCriteria($request));

            $with = [
                'airline',
                'fares',
                'field_values',
                'simbrief' => function ($query) use ($user) {
                    return $query->with('aircraft')->where('user_id', $user->id);
                },
            ];

            $relations = [
                'subfleets',
            ];

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

            $flights = $this->flightRepo->with($with)->paginate();
        } catch (RepositoryException $e) {
            return response($e, 503);
        }

        // TODO: Remove any flights here that a user doesn't have permissions to
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
        /** @var User $user */
        $user = Auth::user();
        $w = [
            'id' => $id,
        ];

        /** @var ?SimBrief $simbrief */
        $simbrief = SimBrief::where($w)->first();

        if ($simbrief === null) {
            throw new AssetNotFound(new Exception('Flight briefing not found'));
        }

        /*if ($simbrief->user_id !== $user->id) {
            throw new Unauthorized(new Exception('User cannot access another user\'s simbrief'));
        }*/

        return response($simbrief->acars_xml, 200, [
            'Content-Type' => 'application/xml',
        ]);
    }

    /**
     * Get a flight's route
     */
    public function route(string $id, Request $request): AnonymousResourceCollection
    {
        $flight = $this->flightRepo->find($id);
        $route = $this->flightSvc->getRoute($flight);

        return NavdataResource::collection($route);
    }

    /**
     * Get a flight's aircrafts
     */
    public function aircraft(string $id, Request $request)
    {
        $flight = $this->flightRepo->with('subfleets')->find($id);

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

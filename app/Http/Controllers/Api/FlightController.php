<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Controller;
use App\Enums\AircraftState;
use App\Enums\AircraftStatus;
use App\Exceptions\AssetNotFound;
use App\Http\Requests\SearchFlightsRequest;
use App\Http\Resources\FlightResource;
use App\Http\Resources\NavdataResource;
use App\Models\Aircraft;
use App\Models\Bid;
use App\Models\Flight;
use App\Models\SimBrief;
use App\Models\User;
use App\Queries\FlightSearchQuery;
use App\Services\FareService;
use App\Services\FlightService;
use Exception;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
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
    ) {}

    /**
     * Return all the flights, paginated
     */
    public function index(SearchFlightsRequest $request): AnonymousResourceCollection
    {
        return $this->search($request);
    }

    public function get(string $id, Request $request): FlightResource
    {
        /** @var User $user */
        $user = Auth::user();

        /** @var Flight $flight */
        $flight = Flight::query()
            ->with([
                'airline',
                'fares',
                'field_values',
                'simbrief' => fn ($query) => $query->with('aircraft')->where('user_id', $user->id),
            ])
            ->findOrFail($id);

        if ($this->hasBidToken($request)) {
            $this->decorateBidSubfleets([$flight], $user->id);
        } else {
            $flight->setRelation(
                'subfleets',
                $flight->accessibleSubfleetsFor($user, ['aircraft', 'fares']),
            );
        }

        $flight = $this->fareSvc->getReconciledFaresForFlight($flight);

        return new FlightResource($flight);
    }

    public function search(SearchFlightsRequest $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = Auth::user();

        $onlyActive = !$request->filled('flight_id');

        $query = $this->flightSearchQuery->build($request, $onlyActive)
            ->whereHas('airline', function ($q): void {
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
            'simbrief' => fn ($q) => $q->with('aircraft')->where('user_id', $user->id),
        ];

        $relations = ['subfleets'];
        if ($request->has('with')) {
            $relations = array_map(trim(...), explode(',', (string) $request->input('with', '')));
        }

        $withBid = in_array('bid', $relations, true);

        $query->with($with);

        if (!$withBid && in_array('subfleets', $relations, true)) {
            $query->withAccessibleSubfleets($user);
        }

        $perPage = paginate_limit($request->integer('limit') ?: null);
        $flights = $query->paginate($perPage);

        if ($withBid) {
            $this->decorateBidSubfleets($flights->getCollection(), $user->id);
        }

        foreach ($flights as $flight) {
            $this->fareSvc->getReconciledFaresForFlight($flight);
        }

        return FlightResource::collection($flights);
    }

    /**
     * Check whether the request carries the controlled `bid` token in `?with=`.
     */
    private function hasBidToken(Request $request): bool
    {
        if (!$request->has('with')) {
            return false;
        }

        $tokens = array_map(trim(...), explode(',', (string) $request->input('with', '')));

        return in_array('bid', $tokens, true);
    }

    /**
     * Set each flight's `subfleets` relation to exactly the authenticated
     * user's bid subfleet(s) on that flight, resolved in a single batched
     * query. Loads `bid.aircraft.subfleet` with its aircraft + fares — the
     * same eager set the legacy accessible-fleet path loads, so serialization
     * (SubfleetResource reads `->aircraft`) does not lazy-load. No fleet
     * expansion is performed; a flight with no bid gets an empty collection.
     *
     * @param iterable<Flight> $flights
     */
    private function decorateBidSubfleets(iterable $flights, int $userId): void
    {
        $flights = collect($flights);

        if ($flights->isEmpty()) {
            return;
        }

        $bidsByFlight = Bid::whereIn('flight_id', $flights->pluck('id'))
            ->where('user_id', $userId)
            ->with('aircraft.subfleet.aircraft', 'aircraft.subfleet.fares')
            ->get()
            ->groupBy('flight_id');

        foreach ($flights as $flight) {
            $subfleets = ($bidsByFlight->get($flight->id) ?? collect())
                ->pluck('aircraft.subfleet')
                ->filter()
                ->unique('id')
                ->values();

            $flight->setRelation('subfleets', new EloquentCollection($subfleets->all()));
        }
    }

    /**
     * Output the flight briefing from simbrief or whatever other format
     *
     * @param string $id The flight ID
     */
    public function briefing(string $id): ResponseFactory|Response
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
        $flight = Flight::findOrFail($id);

        /** @var User $user */
        $user = Auth::user();

        return Aircraft::query()
            ->allowedFor($user, $flight)
            ->where('state', AircraftState::PARKED)
            ->where('status', AircraftStatus::ACTIVE)
            ->when(
                $flight->subfleets()->exists(),
                fn ($q) => $q->whereIn('subfleet_id', $flight->subfleets()->pluck('subfleets.id')),
            )
            ->withCount([
                'bid',
                'simbriefs' => fn ($q) => $q->whereNull('pirep_id'),
            ])
            ->when(
                setting('simbrief.block_aircraft'),
                fn ($q) => $q->having('simbriefs_count', 0),
            )
            ->orderBy('icao')
            ->orderBy('registration')
            ->get();
    }
}

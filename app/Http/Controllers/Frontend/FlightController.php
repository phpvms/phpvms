<?php

namespace App\Http\Controllers\Frontend;

use App\Contracts\Controller;
use App\Http\Requests\SearchFlightsRequest;
use App\Models\Aircraft;
use App\Models\Airline;
use App\Models\Bid;
use App\Models\Enums\FlightType;
use App\Models\Flight;
use App\Models\Subfleet;
use App\Models\Typerating;
use App\Models\User;
use App\Queries\FlightSearchQuery;
use App\Services\FlightService;
use App\Services\GeoService;
use App\Services\ModuleService;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Laracasts\Flash\Flash;

class FlightController extends Controller
{
    public function __construct(
        private readonly FlightSearchQuery $flightSearchQuery,
        private readonly FlightService $flightSvc,
        private readonly GeoService $geoSvc,
        private readonly ModuleService $moduleSvc,
        private readonly UserService $userSvc
    ) {}

    public function index(SearchFlightsRequest $request): View
    {
        return $this->search($request);
    }

    /**
     * Build the flight search using FlightSearchQuery.
     */
    public function search(SearchFlightsRequest $request): View
    {
        // FlightSearchQuery::build() already applies active+visible via
        // model scopes when $onlyActive=true (the default). $where here is
        // strictly for caller-owned per-user restrictions on top of that.
        $where = [];

        /** @var User $user */
        $user = Auth::user();
        $user->loadMissing(['current_airport', 'typeratings']);

        if (setting('pilots.restrict_to_company')) {
            $where['airline_id'] = $user->airline_id;
        }

        // default restrictions on the flights shown. Handle search differently
        if (setting('pilots.only_show_flights_from_current')) {
            $where['dpt_airport_id'] = $user->curr_airport_id;
        }

        // Filter flights according to user capabilities (by rank or by type rating etc)
        $filter_by_user = setting('pireps.restrict_aircraft_to_rank', true) || setting('pireps.restrict_aircraft_to_typerating', false);

        if ($filter_by_user) {
            // Get allowed subfleets for the user
            $user_subfleets = $this->userSvc->getAllowableSubfleets($user)->pluck('id')->toArray();
            $allowed_flights = $this->flightSvc->getAccessibleFlightIds($user);
            // Build aircraft icao codes by considering allowed subfleets
            $icao_codes = Aircraft::whereIn('subfleet_id', $user_subfleets)->groupBy('icao')->orderBy('icao')->pluck('icao')->toArray();
            // Build type ratings collection by considering user's capabilities
            $type_ratings = $user->typeratings;
        } else {
            $allowed_flights = [];
            // Build aircraft icao codes array from complete fleet
            $icao_codes = Aircraft::groupBy('icao')->orderBy('icao')->pluck('icao')->toArray();
            // Build type ratings collection from all active ratings
            $type_ratings = Typerating::where('active', 1)->select('id', 'name', 'type')->orderBy('type')->get();
        }

        // Get only used Flight Types for the search form
        // And filter according to settings (active/visible apply here too —
        // we don't want flight_type options surfacing from hidden flights).
        $usedtypes = Flight::select('flight_type')
            ->active()
            ->visible()
            ->where($where)
            ->groupby('flight_type')
            ->orderby('flight_type')
            ->get();

        // Build collection with type codes and labels
        /** @var Collection<string, string> $flight_types */
        $flight_types = collect();
        foreach ($usedtypes as $ftype) {
            $flight_types->put($ftype->flight_type, FlightType::label($ftype->flight_type));
        }

        $query = $this->flightSearchQuery->build($request)
            ->whereHas('airline', function ($q) {
                $q->where('active', true);
            });

        // Apply controller-owned restrictions (previous WhereCriteria $where)
        foreach ($where as $col => $val) {
            $query->where($col, $val);
        }

        $flights = $query
            ->with([
                'airline',
                'alt_airport',
                'arr_airport',
                'dpt_airport',
                'subfleets.airline',
                'simbrief' => function ($q) use ($user) {
                    $q->where('user_id', $user->id);
                },
            ])
            ->when($filter_by_user, function ($q) use ($allowed_flights) {
                return $q->whereIn('id', $allowed_flights);
            })
            ->orderBy('route_code')->orderBy('route_leg')
            ->paginate($request->integer('limit') ?: null);

        $saved_flights = [];
        $bids = Bid::where('user_id', Auth::id())->get();
        foreach ($bids as $bid) {
            if (!$bid->flight) {
                $bid->delete();

                continue;
            }

            $saved_flights[$bid->flight_id] = $bid->id;
        }

        return view('flights.index', [
            'user'          => $user,
            'airlines'      => Airline::selectList(addBlank: true),
            'airports'      => [],
            'flights'       => $flights,
            'saved'         => $saved_flights,
            'subfleets'     => $this->subfleetSelectBoxList(true),
            'flight_number' => $request->input('flight_number'),
            'flight_types'  => $flight_types,
            'flight_type'   => $request->input('flight_type'),
            'arr_icao'      => $request->input('arr_icao'),
            'dep_icao'      => $request->input('dep_icao'),
            'subfleet_id'   => $request->input('subfleet_id'),
            'simbrief'      => !empty(setting('simbrief.api_key')),
            'simbrief_bids' => setting('simbrief.only_bids'),
            'acars_plugin'  => $this->moduleSvc->isModuleActive('VMSAcars'),
            'icao_codes'    => $icao_codes,
            'type_ratings'  => $type_ratings,
        ]);
    }

    /**
     * Find the user's bids and display them
     */
    public function bids(Request $request): View
    {
        $user = User::with(['bids', 'bids.flight'])->findOrFail(Auth::id());

        $flights = collect();
        $saved_flights = [];
        foreach ($user->bids as $bid) {
            // Remove any invalid bids (flight doesn't exist or something)
            if (!$bid->flight) {
                $bid->delete();

                continue;
            }

            $flights->add($bid->flight);
            $saved_flights[$bid->flight_id] = $bid->id;
        }

        return view('flights.bids', [
            'user'          => $user,
            'airlines'      => Airline::selectList(addBlank: true),
            'airports'      => [],
            'flights'       => $flights,
            'saved'         => $saved_flights,
            'subfleets'     => $this->subfleetSelectBoxList(true),
            'simbrief'      => !empty(setting('simbrief.api_key')),
            'simbrief_bids' => setting('simbrief.only_bids'),
            'acars_plugin'  => $this->moduleSvc->isModuleActive('VMSAcars'),
        ]);
    }

    /**
     * Show the flight information page
     */
    public function show(string $id): View|RedirectResponse
    {
        $user = Auth::user();
        // Support retrieval of deleted relationships
        $with_flight = [
            'airline' => function ($query) {
                return $query->withTrashed();
            },
            'alt_airport' => function ($query) {
                return $query->withTrashed();
            },
            'arr_airport' => function ($query) {
                return $query->withTrashed();
            },
            'dpt_airport' => function ($query) {
                return $query->withTrashed();
            },
            'subfleets.airline',
            'simbrief' => function ($query) use ($user) {
                $query->where('user_id', $user->id);
            },
        ];

        $flight = Flight::with($with_flight)->find($id);
        if (empty($flight)) {
            Flash::error('Flight not found!');

            return redirect(route('frontend.dashboard.index'));
        }

        if (setting('flights.only_company_aircraft', false)) {
            $flight = $this->flightSvc->filterSubfleets($user, $flight);
        }

        $map_features = $this->geoSvc->flightGeoJson($flight);

        // See if the user has a bid for this flight
        $bid = Bid::where(['user_id' => $user->id, 'flight_id' => $flight->id])->first();

        return view('flights.show', [
            'flight'       => $flight,
            'map_features' => $map_features,
            'bid'          => $bid,
            'acars_plugin' => $this->moduleSvc->isModuleActive('VMSAcars'),
        ]);
    }

    /**
     * Build a [id => "Name | ICAO"] options map for subfleet select boxes.
     * Ported from the deleted SubfleetRepository::selectBoxList().
     */
    private function subfleetSelectBoxList(bool $add_blank = false): array
    {
        $retval = [];
        $items = Subfleet::with('airline')->get();

        if ($add_blank) {
            $retval[''] = '';
        }

        foreach ($items as $i) {
            // airline_id is nullable on Subfleet, so guard against an
            // orphan/soft-deleted airline before reading icao.
            $icao = $i->airline === null ? '—' : $i->airline->icao;
            $retval[$i->id] = $i->name.' | '.$icao;
        }

        return $retval;
    }
}

<?php

namespace App\Http\Controllers\Frontend;

use App\Addons\AddonRegistry;
use App\Contracts\Controller;
use App\Exceptions\BidExistsForAircraft;
use App\Exceptions\BidExistsForFlight;
use App\Exceptions\UserBidLimit;
use App\Http\Data\BidRowData;
use App\Http\Data\BidSelectionData;
use App\Http\Data\EligibleAircraftData;
use App\Http\Data\EligibleSubfleetData;
use App\Http\Data\FlightDetailData;
use App\Http\Data\FlightDispatchPolicyData;
use App\Http\Data\FlightListItemData;
use App\Http\Requests\SearchFlightsRequest;
use App\Http\Requests\StoreFlightBidRequest;
use App\Models\Aircraft;
use App\Models\Airline;
use App\Models\Bid;
use App\Models\Flight;
use App\Models\Subfleet;
use App\Models\Typerating;
use App\Models\User;
use App\Queries\FlightSearchQuery;
use App\Services\BidService;
use App\Services\GeoService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Inertia\Response as InertiaResponse;
use Laracasts\Flash\Flash;

class FlightController extends Controller
{
    public function __construct(
        private readonly FlightSearchQuery $flightSearchQuery,
        private readonly GeoService $geoSvc,
        private readonly AddonRegistry $addonRegistry,
        private readonly BidService $bidSvc,
    ) {}

    private function acarsEnabled(): bool
    {
        return (bool) $this->addonRegistry->find('VMSAcars')?->isEnabled();
    }

    public function index(SearchFlightsRequest $request): View|InertiaResponse
    {
        return $this->search($request);
    }

    /**
     * Build the flight search using FlightSearchQuery.
     */
    public function search(SearchFlightsRequest $request): View|InertiaResponse
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

        // Fleet fields are search criteria. They must not execute the current
        // aircraft eligibility policy that applies only after a pilot starts a bid.
        $icao_codes = Aircraft::query()->groupBy('icao')->orderBy('icao')->pluck('icao')->toArray();
        $type_ratings = Typerating::query()->where('active', true)->select('id', 'name', 'type')->orderBy('type')->get();

        // Get only used Flight Types for the search form
        // And filter according to settings (active/visible apply here too —
        // we don't want flight_type options surfacing from hidden flights).
        $usedtypes = Flight::select('flight_type')
            ->visible()
            ->where($where)
            ->groupby('flight_type')
            ->orderby('flight_type')
            ->get();

        // Build collection with type codes and labels
        /** @var Collection<string, string> $flight_types */
        $flight_types = collect();
        foreach ($usedtypes as $ftype) {
            $flight_types->put($ftype->flight_type->value, $ftype->flight_type->getLabel());
        }

        $query = $this->flightSearchQuery->build($request)
            ->whereHas('airline', function ($q): void {
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
                'simbrief' => function ($q) use ($user): void {
                    $q->where('user_id', $user->id);
                },
            ])
            ->orderBy('route_code')->orderBy('route_leg')
            ->paginate(paginate_limit($request->integer('limit') ?: null));

        $saved_flights = [];
        $bids = Bid::with('flight')->where('user_id', Auth::id())->get();
        foreach ($bids as $bid) {
            if (!$bid->flight) {
                $bid->delete();

                continue;
            }

            $saved_flights[$bid->flight_id] = $bid->id;
        }

        $pilotAtLimit = !(bool) setting('bids.allow_multiple_bids', false) && $bids->isNotEmpty();
        $policy = FlightDispatchPolicyData::fromSettings($pilotAtLimit);

        $viewData = [
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
            'acars_plugin'  => $this->acarsEnabled(),
            'icao_codes'    => $icao_codes,
            'type_ratings'  => $type_ratings,
        ];

        return response()->themed(
            'Flights',
            'flights.index',
            bladeData: $viewData,
            spa: fn (): array => [
                'flights' => collect($flights->items())
                    ->map(fn (Flight $f): FlightListItemData => FlightListItemData::fromModel(
                        $f,
                        $saved_flights,
                        $policy,
                        $pilotAtLimit,
                        $policy->requireCurrentAirport && $f->dpt_airport_id !== $user->curr_airport_id,
                    ))
                    ->values()
                    ->all(),
                'policy' => $policy,
                'page'   => [
                    'current' => $flights->currentPage(),
                    'last'    => $flights->lastPage(),
                    'total'   => $flights->total(),
                ],
                'filters' => [
                    'airlineId'           => $request->input('airline_id'),
                    'flightNumber'        => $request->input('flight_number'),
                    'flightType'          => $request->input('flight_type'),
                    'routeCode'           => $request->input('route_code'),
                    'depIcao'             => $request->input('dep_icao', $request->input('dpt_airport_id')),
                    'arrIcao'             => $request->input('arr_icao', $request->input('arr_airport_id')),
                    'distanceGreaterThan' => $request->input('dgt'),
                    'distanceLessThan'    => $request->input('dlt'),
                    'timeGreaterThan'     => $request->input('tgt'),
                    'timeLessThan'        => $request->input('tlt'),
                    'subfleetId'          => $request->input('subfleet_id'),
                    'typeRatingId'        => $request->input('type_rating_id'),
                    'icaoType'            => $request->input('icao_type'),
                    'search'              => $request->input('search'),
                    'orderBy'             => $request->input('orderBy'),
                    'sortedBy'            => $request->input('sortedBy'),
                    'limit'               => $request->input('limit'),
                ],
                'filterOptions' => [
                    'airlines'    => $viewData['airlines'],
                    'flightTypes' => $viewData['flight_types']->all(),
                    'subfleets'   => $viewData['subfleets'],
                    'typeRatings' => $viewData['type_ratings']->map(fn ($rating): array => [
                        'id'   => $rating->id,
                        'name' => $rating->name,
                        'type' => $rating->type,
                    ])->values()->all(),
                    'icaoTypes' => $viewData['icao_codes'],
                ],
            ],
        );
    }

    /**
     * Find the user's bids and display them
     */
    public function bids(Request $request): View|InertiaResponse
    {
        // Eager-load bids + their flights + each flight's airline (the app
        // enforces preventLazyLoading, so neither the Blade bids table — which
        // reads $flight->airline — nor the presenter may touch an unloaded
        // relation). Loading bids.flight.airline here covers both paths.
        $user = User::with([
            'bids.aircraft.airport',
            'bids.aircraft.subfleet',
            'bids.flight.airline',
            'bids.flight.arr_airport',
            'bids.flight.dpt_airport',
        ])->findOrFail(Auth::id());

        $flights = collect();
        $valid_bids = collect();
        $saved_flights = [];
        foreach ($user->bids as $bid) {
            // Remove any invalid bids (flight doesn't exist or something)
            if (!$bid->flight) {
                $bid->delete();

                continue;
            }

            $flights->add($bid->flight);
            $valid_bids->add($bid);
            $saved_flights[$bid->flight_id] = $bid->id;
        }

        $viewData = [
            'user'          => $user,
            'airlines'      => Airline::selectList(addBlank: true),
            'airports'      => [],
            'flights'       => $flights,
            'saved'         => $saved_flights,
            'subfleets'     => $this->subfleetSelectBoxList(true),
            'simbrief'      => !empty(setting('simbrief.api_key')),
            'simbrief_bids' => setting('simbrief.only_bids'),
            'acars_plugin'  => $this->acarsEnabled(),
        ];
        $policy = FlightDispatchPolicyData::fromSettings(
            !(bool) setting('bids.allow_multiple_bids', false) && $valid_bids->isNotEmpty(),
        );

        // Dual output from ONE data-gathering path (no presenter): Blade gets the
        // model-rich $viewData verbatim (relations/value-objects/queries intact);
        // the SPA gets flat, typed BidRowData DTOs — built lazily (only on the SPA
        // theme) from the same eager-loaded Bid models, so no N+1.
        return response()->themed(
            'Flights/Bids',
            'flights.bids',
            bladeData: $viewData,
            spa: fn (): array => [
                'bids' => $valid_bids
                    ->map(fn (Bid $bid): BidRowData => BidRowData::fromModel($bid, $policy, $saved_flights))
                    ->values()
                    ->all(),
                'policy' => $policy,
            ],
        );
    }

    /**
     * Show the flight information page
     */
    public function show(string $id): View|RedirectResponse|InertiaResponse
    {
        /** @var User $user */
        $user = Auth::user();
        // Support retrieval of deleted relationships
        $with_flight = [
            'airline'     => fn ($query) => $query->withTrashed(),
            'alt_airport' => fn ($query) => $query->withTrashed(),
            'arr_airport' => fn ($query) => $query->withTrashed(),
            'dpt_airport' => fn ($query) => $query->withTrashed(),
            'subfleets.airline',
            'simbrief' => function ($query) use ($user): void {
                $query->where('user_id', $user->id);
            },
        ];

        $flight = $this->accessibleFlightQuery($user)
            ->with($with_flight)
            ->find($id);
        if (empty($flight)) {
            Flash::error('Flight not found!');

            return redirect(route('frontend.dashboard.index'));
        }

        $flight->setRelation(
            'subfleets',
            $flight->accessibleSubfleetsFor($user, ['airline']),
        );

        $map_features = $this->geoSvc->flightGeoJson($flight);

        // See if the user has a bid for this flight
        $bid = Bid::where(['user_id' => $user->id, 'flight_id' => $flight->id])->first();
        $pilotAtLimit = !(bool) setting('bids.allow_multiple_bids', false)
            && Bid::query()->where('user_id', $user->id)->exists();
        $policy = FlightDispatchPolicyData::fromSettings($pilotAtLimit);

        $bladeData = [
            'flight'       => $flight,
            'map_features' => $map_features,
            'bid'          => $bid,
            'acars_plugin' => $this->acarsEnabled(),
        ];

        return response()->themed(
            'Flights/Show',
            'flights.show',
            bladeData: $bladeData,
            spa: fn (): array => [
                'flight' => FlightDetailData::fromModel(
                    $flight,
                    $bid ? [$flight->id => $bid->id] : [],
                    $policy,
                    $pilotAtLimit,
                    $policy->requireCurrentAirport && $flight->dpt_airport_id !== $user->curr_airport_id,
                ),
                'policy' => $policy,
            ],
        );
    }

    public function dispatchData(string $id): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $flight = $this->accessibleFlightQuery($user)
            ->with(['airline', 'alt_airport', 'arr_airport', 'dpt_airport'])
            ->findOrFail($id);
        $bid = Bid::query()
            ->where('user_id', $user->id)
            ->where('flight_id', $flight->id)
            ->first();
        $pilotAtLimit = !(bool) setting('bids.allow_multiple_bids', false)
            && Bid::query()->where('user_id', $user->id)->exists();
        $policy = FlightDispatchPolicyData::fromSettings($pilotAtLimit);

        return response()->json([
            'flight' => FlightDetailData::fromModel(
                $flight,
                $bid ? [$flight->id => $bid->id] : [],
                $policy,
                $pilotAtLimit,
                $policy->requireCurrentAirport && $flight->dpt_airport_id !== $user->curr_airport_id,
            ),
            'policy'    => $policy,
            'subfleets' => $this->bidSvc->configuredSubfleets($flight)
                ->map(function (Subfleet $subfleet) use ($flight, $user): EligibleSubfleetData {
                    $eligibleAircraftCount = $this->bidSvc
                        ->eligibleAircraftQuery($flight, $user, $subfleet->id)
                        ->count();

                    return EligibleSubfleetData::fromModel($subfleet, $eligibleAircraftCount);
                })
                ->values(),
            'selection' => $bid ? BidSelectionData::fromModel($bid, $policy) : null,
        ]);
    }

    public function dispatchAircraft(string $id, int $subfleetId): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $flight = $this->accessibleFlightQuery($user)->findOrFail($id);

        abort_unless(
            $this->bidSvc->configuredSubfleets($flight)->contains('id', $subfleetId),
            404,
        );

        return response()->json([
            'aircraft' => $this->bidSvc->eligibleAircraftQuery($flight, $user, $subfleetId)
                ->get()
                ->map(fn (Aircraft $aircraft): EligibleAircraftData => EligibleAircraftData::fromModel($aircraft))
                ->values(),
        ]);
    }

    public function storeBid(StoreFlightBidRequest $request, string $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $flight = $this->accessibleFlightQuery($user)->findOrFail($id);
        $aircraftId = $request->validated('aircraftId');
        $aircraft = $aircraftId === null ? null : Aircraft::query()->find($aircraftId);
        if ($aircraftId !== null && !($aircraft instanceof Aircraft)) {
            return response()->json([
                'type'    => 'stale-aircraft',
                'message' => 'This aircraft is no longer available. Refresh the aircraft list.',
                'errors'  => ['aircraftId' => ['This aircraft is no longer available.']],
            ], 422);
        }

        try {
            $bid = $this->bidSvc->addBid($flight, $user, $aircraft);
        } catch (UserBidLimit $exception) {
            return $this->bidConflict('pilot-limit', $exception->getMessage());
        } catch (BidExistsForFlight $exception) {
            return $this->bidConflict('flight-conflict', $exception->getMessage());
        } catch (BidExistsForAircraft $exception) {
            return $this->bidConflict('aircraft-conflict', $exception->getMessage());
        } catch (ValidationException $exception) {
            return response()->json([
                'type' => $aircraftId !== null && isset($exception->errors()['aircraftId'])
                    ? 'stale-aircraft'
                    : 'validation',
                'message' => collect($exception->errors())->flatten()->first(),
                'errors'  => $exception->errors(),
            ], 422);
        }

        return response()->json([
            'selection' => BidSelectionData::fromModel($bid, FlightDispatchPolicyData::fromSettings()),
        ]);
    }

    public function destroyBid(Request $request, string $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $flight = $this->accessibleFlightQuery($user)->findOrFail($id);

        $this->bidSvc->removeBid($flight, $user);

        return response()->json([
            'flightUrl' => route('frontend.flights.show', $flight->id),
            'bidsUrl'   => route('frontend.flights.bids'),
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

    /** @return Builder<Flight> */
    private function accessibleFlightQuery(User $user): Builder
    {
        $query = Flight::query()->visible()->whereHas('airline', fn ($airline) => $airline->where('active', true));

        if ((bool) setting('pilots.restrict_to_company', false)) {
            $query->where('airline_id', $user->airline_id);
        }

        return $query;
    }

    private function bidConflict(string $type, string $message): JsonResponse
    {
        return response()->json([
            'type'    => $type,
            'message' => $message,
        ], 409);
    }
}

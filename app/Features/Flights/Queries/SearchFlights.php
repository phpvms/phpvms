<?php

declare(strict_types=1);

namespace App\Features\Flights\Queries;

use App\Http\Data\FlightDispatchPolicyData;
use App\Http\Data\FlightListItemData;
use App\Http\Requests\SearchFlightsRequest;
use App\Models\Aircraft;
use App\Models\Airline;
use App\Models\Bid;
use App\Models\Flight;
use App\Models\Subfleet;
use App\Models\Typerating;
use App\Models\User;
use App\Queries\FlightSearchQuery;
use Illuminate\Support\Facades\Validator;

/**
 * The flight schedule a pilot browses: the same search, restrictions, paging and
 * list-item DTOs as `FlightController::search()`, for a Livewire component that
 * has no request to read the filters from.
 *
 * Filters are keyed by their query-string names (`airline_id`, `dgt`, ...).
 */
final readonly class SearchFlights
{
    public function __construct(private FlightSearchQuery $search) {}

    /**
     * Keep the filled filters that pass the search request's rules.
     *
     * @param  array<string, mixed>                                       $filters
     * @return array{valid: array<string, string>, invalid: list<string>}
     */
    public function sanitize(array $filters): array
    {
        $filled = collect($filters)
            ->filter(fn (mixed $value): bool => $value !== null && $value !== '')
            ->map(fn (mixed $value): string => (string) $value)
            ->all();

        $rules = SearchFlightsRequest::create('/flights', 'GET', $filled)->rules();
        $invalid = Validator::make($filled, $rules)->errors()->keys();

        return [
            'valid'   => array_diff_key($filled, array_flip($invalid)),
            'invalid' => $invalid,
        ];
    }

    /**
     * @param  array<string, string>                                                                                                        $filters Sanitized filters
     * @return array{flights: list<FlightListItemData>, policy: FlightDispatchPolicyData, page: array{current: int, last: int, total: int}}
     */
    public function handle(User $user, array $filters, int $page = 1): array
    {
        $request = SearchFlightsRequest::create('/flights', 'GET', $filters);

        $query = $this->search->build($request)
            ->whereHas('airline', fn ($airline) => $airline->where('active', true));

        foreach ($this->restrictions($user) as $column => $value) {
            $query->where($column, $value);
        }

        $flights = $query
            ->with(['airline', 'alt_airport', 'arr_airport', 'dpt_airport'])
            ->orderBy('route_code')->orderBy('route_leg')
            ->paginate(paginate_limit($request->integer('limit') ?: null), ['*'], 'page', max(1, $page));

        $saved = Bid::query()->where('user_id', $user->id)->pluck('id', 'flight_id')->all();
        $pilotAtLimit = !(bool) setting('bids.allow_multiple_bids', false) && $saved !== [];
        $policy = FlightDispatchPolicyData::fromSettings($pilotAtLimit);

        return [
            'flights' => collect($flights->items())
                ->map(fn (Flight $flight): FlightListItemData => FlightListItemData::fromModel(
                    $flight,
                    $saved,
                    $policy,
                    $pilotAtLimit,
                    $policy->requireCurrentAirport && $flight->dpt_airport_id !== $user->curr_airport_id,
                ))
                ->values()
                ->all(),
            'policy' => $policy,
            'page'   => [
                'current' => $flights->currentPage(),
                'last'    => $flights->lastPage(),
                'total'   => $flights->total(),
            ],
        ];
    }

    /**
     * The choices behind the filter selects.
     *
     * @return array{airlines: array<int|string, string>, flightTypes: array<string, string>, subfleets: array<int|string, string>, typeRatings: list<array{id: int, name: string, type: string}>, icaoTypes: list<string>}
     */
    public function options(User $user): array
    {
        $flightTypes = Flight::query()
            ->select('flight_type')
            ->visible()
            ->where($this->restrictions($user))
            ->groupBy('flight_type')
            ->orderBy('flight_type')
            ->get()
            ->mapWithKeys(fn (Flight $flight): array => [$flight->flight_type->value => $flight->flight_type->getLabel()])
            ->all();

        return [
            'airlines' => setting('pilots.restrict_to_company')
                ? Airline::query()->where('active', true)->whereKey($user->airline_id)->pluck('name', 'id')->all()
                : Airline::selectList(addBlank: true),
            'flightTypes' => $flightTypes,
            'subfleets'   => Subfleet::with('airline')->get()
                ->mapWithKeys(fn (Subfleet $subfleet): array => [$subfleet->id => $subfleet->name.' | '.($subfleet->airline->icao ?? '—')])
                ->all(),
            'typeRatings' => Typerating::query()->where('active', true)->select('id', 'name', 'type')->orderBy('type')->get()
                ->map(fn (Typerating $rating): array => ['id' => $rating->id, 'name' => $rating->name, 'type' => $rating->type])
                ->values()
                ->all(),
            'icaoTypes' => Aircraft::query()->groupBy('icao')->orderBy('icao')->pluck('icao')->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function restrictions(User $user): array
    {
        $where = [];

        if (setting('pilots.restrict_to_company')) {
            $where['airline_id'] = $user->airline_id;
        }

        if (setting('pilots.only_show_flights_from_current')) {
            $where['dpt_airport_id'] = $user->curr_airport_id;
        }

        return $where;
    }
}

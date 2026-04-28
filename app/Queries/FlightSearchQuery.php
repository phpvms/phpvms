<?php

declare(strict_types=1);

namespace App\Queries;

use App\Http\Requests\SearchFlightsRequest;
use App\Models\Flight;
use Illuminate\Database\Eloquent\Builder;

/**
 * Builds the Eloquent query for Flight list/search endpoints.
 *
 * Replicates behavior previously driven by FlightRepository::searchCriteria
 * + WhereCriteria + RequestCriteria. Mirrors the shape of PirepSearchQuery
 * and UserSearchQuery: one public build() method returning a Builder, with
 * private apply* helpers per concern. Caller decides ->paginate() vs ->get()
 * and adds with[] / extra ->where filters as needed.
 */
class FlightSearchQuery
{
    /**
     * Build the search query.
     *
     * @param SearchFlightsRequest $request    Validated query string.
     * @param bool                 $onlyActive When true, restricts to active=true AND visible=true.
     */
    /**
     * @return Builder<Flight>
     */
    public function build(SearchFlightsRequest $request, bool $onlyActive = true): Builder
    {
        $query = Flight::query();

        if ($onlyActive) {
            $query->active()->visible();
        }

        $this->applyExactFilters($query, $request);
        $this->applyAirportFilters($query, $request);
        $this->applyRangeFilters($query, $request);
        $this->applyRelationFilters($query, $request);
        $this->applyOrdering($query, $request);

        return $query;
    }

    /**
     * @param Builder<Flight> $query
     */
    private function applyExactFilters(Builder $query, SearchFlightsRequest $request): void
    {
        $query
            ->when($request->filled('flight_id'), fn (Builder $q) => $q->where('id', $request->input('flight_id')))
            ->when($request->filled('airline_id'), fn (Builder $q) => $q->forAirline((int) $request->input('airline_id')))
            ->when($request->filled('flight_number'), fn (Builder $q) => $q->where('flight_number', $request->input('flight_number')))
            ->when($request->filled('callsign'), fn (Builder $q) => $q->where('callsign', $request->input('callsign')))
            ->when($request->filled('route_code'), fn (Builder $q) => $q->where('route_code', $request->input('route_code')));

        // flight_type='0' is treated as "no filter" by legacy code.
        if ($request->filled('flight_type') && $request->input('flight_type') !== '0') {
            $query->withFlightType((string) $request->input('flight_type'));
        }
    }

    /**
     * @param Builder<Flight> $query
     */
    private function applyAirportFilters(Builder $query, SearchFlightsRequest $request): void
    {
        // dep_icao is an alias for dpt_airport_id; both uppercased.
        $departure = $request->input('dpt_airport_id') ?? $request->input('dep_icao');
        if (filled($departure)) {
            $query->fromAirport((string) $departure);
        }

        // arr_icao is an alias for arr_airport_id; both uppercased.
        $arrival = $request->input('arr_airport_id') ?? $request->input('arr_icao');
        if (filled($arrival)) {
            $query->toAirport((string) $arrival);
        }
    }

    /**
     * @param Builder<Flight> $query
     */
    private function applyRangeFilters(Builder $query, SearchFlightsRequest $request): void
    {
        $query
            ->when($request->filled('dgt'), fn (Builder $q) => $q->distanceAtLeast((int) $request->input('dgt')))
            ->when($request->filled('dlt'), fn (Builder $q) => $q->distanceAtMost((int) $request->input('dlt')))
            ->when($request->filled('tgt'), fn (Builder $q) => $q->flightTimeAtLeast((int) $request->input('tgt')))
            ->when($request->filled('tlt'), fn (Builder $q) => $q->flightTimeAtMost((int) $request->input('tlt')));
    }

    /**
     * @param Builder<Flight> $query
     */
    private function applyRelationFilters(Builder $query, SearchFlightsRequest $request): void
    {
        $query
            ->when($request->filled('subfleet_id'), fn (Builder $q) => $q->withSubfleet((int) $request->input('subfleet_id')))
            ->when($request->filled('type_rating_id'), fn (Builder $q) => $q->forTypeRating((int) $request->input('type_rating_id')))
            ->when($request->filled('icao_type'), fn (Builder $q) => $q->withIcaoType((string) $request->input('icao_type')));
    }

    /**
     * @param Builder<Flight> $query
     */
    private function applyOrdering(Builder $query, SearchFlightsRequest $request): void
    {
        $orderBy = $request->input('orderBy');
        if (!$orderBy) {
            return;
        }

        $direction = strtolower((string) $request->input('sortedBy', 'asc'));
        if (!in_array($direction, ['asc', 'desc'], true)) {
            $direction = 'asc';
        }

        $query->orderBy($orderBy, $direction);
    }
}

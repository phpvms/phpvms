<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Service;
use App\Enums\PirepState;
use App\Enums\PirepStatus;
use App\Exceptions\DuplicateFlight;
use App\Models\Bid;
use App\Models\Flight;
use App\Models\FlightFieldValue;
use App\Models\Navdata;
use App\Models\Pirep;
use App\Models\User;
use App\Support\Days;
use App\Support\Units\Time;
use Exception;
use Illuminate\Support\Collection;

class FlightService extends Service
{
    public function __construct(
        private readonly AirportService $airportSvc,
    ) {}

    /**
     * Create a new flight
     *
     * ICAO normalization (uppercase + trim) is handled by the `dpt_airport_id`
     * / `arr_airport_id` Attribute mutators on `Flight`, which fire during
     * `new Flight($fields)` → `fill()` below. After fill, the canonical
     * values are mirrored back into `$fields` so the downstream airport
     * lookup + `transformFlightFields` distance calc both see the normalized
     * ICAOs (AirportService::calculateDistance does not case-fold its inputs).
     */
    public function createFlight(array $fields): Flight
    {
        $flightTmp = new Flight($fields);
        if ($this->isFlightDuplicate($flightTmp)) {
            throw new DuplicateFlight($flightTmp);
        }

        $fields = $this->syncNormalizedAirportIds($fields, $flightTmp);

        $this->airportSvc->lookupAirportIfNotFound($fields['dpt_airport_id']);
        $this->airportSvc->lookupAirportIfNotFound($fields['arr_airport_id']);

        $fields = $this->transformFlightFields($fields);

        return Flight::create($fields);
    }

    /**
     * Update a flight with values from the given fields
     *
     * Same mutator-driven ICAO normalization as `createFlight`: `fill()`
     * triggers the `dpt_airport_id` / `arr_airport_id` Attribute setters
     * before the duplicate check reads the normalized values, then the
     * canonical values get mirrored back into `$fields` for the downstream
     * distance calc.
     */
    public function updateFlight(Flight $flight, array $fields): Flight
    {
        // apply the updates here temporarily, don't save
        // the duplicate check uses the in-memory state
        $flight->fill($fields);

        if ($this->isFlightDuplicate($flight)) {
            throw new DuplicateFlight($flight);
        }

        $fields = $this->syncNormalizedAirportIds($fields, $flight);

        $fields = $this->transformFlightFields($fields);

        $flight->update($fields);

        return $flight->refresh();
    }

    /**
     * Mirror the model's mutator-normalized ICAOs back into the `$fields`
     * array. `transformFlightFields` reads `$fields['dpt_airport_id']` /
     * `$fields['arr_airport_id']` to compute distance via
     * `AirportService::calculateDistance`, which does NOT case-fold its
     * inputs — so the array values need to match the model's canonical form.
     *
     * @param  array<string, mixed> $fields
     * @return array<string, mixed>
     */
    private function syncNormalizedAirportIds(array $fields, Flight $flight): array
    {
        if (array_key_exists('dpt_airport_id', $fields)) {
            $fields['dpt_airport_id'] = $flight->dpt_airport_id;
        }

        if (array_key_exists('arr_airport_id', $fields)) {
            $fields['arr_airport_id'] = $flight->arr_airport_id;
        }

        return $fields;
    }

    /**
     * Check the fields for a flight and transform them
     */
    protected function transformFlightFields(array $fields): array
    {
        if (array_key_exists('days', $fields) && filled($fields['days'])) {
            $fields['days'] = Days::getDaysMask($fields['days']);
        }

        $fields['flight_time'] = Time::init($fields['minutes'], $fields['hours'])->getMinutes();
        $fields['active'] = get_truth_state($fields['active']);

        // Figure out a distance if not found
        if (empty($fields['distance'])) {
            $fields['distance'] = $this->airportSvc->calculateDistance(
                $fields['dpt_airport_id'],
                $fields['arr_airport_id']
            );
        }

        return $fields;
    }

    /**
     * Return flight IDs a user may see when aircraft restrictions are active.
     * Includes flights with at least one allowed subfleet and true open flights.
     *
     * @return list<string>
     */
    public function getAccessibleFlightIds(User $user): array
    {
        $userSubfleets = $user->allowedSubfleets()->pluck('id')->all();

        $userFlights = Flight::query()
            ->whereHas('subfleets', static function ($query) use ($userSubfleets): void {
                $query->whereIn('subfleets.id', $userSubfleets);
            })
            ->pluck('id')
            ->all();

        $openFlights = Flight::query()
            ->whereNull('user_id')
            ->doesntHave('subfleets')
            ->pluck('id')
            ->all();

        return array_values(array_unique(array_merge($userFlights, $openFlights)));
    }

    /**
     * Check if this flight has a duplicate already.
     *
     * Same airline + flight_number + route_code + route_leg + departure +
     * arrival + days is treated as a duplicate. Resolved at the DB layer to
     * avoid pulling matching rows into memory just to answer a boolean.
     * route_code / route_leg / days are nullable, so null-vs-null matches
     * are handled explicitly (SQL `=` returns null when either side is null).
     */
    public function isFlightDuplicate(Flight $flight): bool
    {
        $query = Flight::query()
            ->where('airline_id', $flight->airline_id)
            ->where('flight_number', $flight->flight_number)
            ->whereNull('owner_type')
            ->where('dpt_airport_id', $flight->dpt_airport_id)
            ->where('arr_airport_id', $flight->arr_airport_id);

        // Exclude self only when the input flight is persisted; for an unsaved
        // model, $flight->id is null and `id <> NULL` matches nothing in SQL.
        if ($flight->exists) {
            $query->where('id', '<>', $flight->id);
        }

        // Match nullable scalar columns including legacy empty-string values.
        // route_code is a string column — empty strings are valid on all DBs.
        // route_leg and days are integer columns — PG cannot compare int to '',
        // so only check null and 0.
        $stringColumns = ['route_code'];
        $integerColumns = ['route_leg', 'days'];

        foreach ($stringColumns as $column) {
            $value = $flight->{$column};

            if (in_array($value, [null, '', '0'], true)) {
                $query->where(function ($q) use ($column): void {
                    $q->whereNull($column)
                        ->orWhere($column, '')
                        ->orWhere($column, 0);
                });
            } else {
                $query->where($column, $value);
            }
        }

        foreach ($integerColumns as $column) {
            $value = $flight->{$column};

            if (in_array($value, [null, '', 0, '0'], true)) {
                $query->where(function ($q) use ($column): void {
                    $q->whereNull($column)
                        ->orWhere($column, 0);
                });
            } else {
                $query->where($column, $value);
            }
        }

        return $query->exists();
    }

    /**
     * Delete a flight, and all the user bids, etc associated with it
     *
     *
     * @throws Exception
     */
    public function deleteFlight(Flight $flight): void
    {
        $where = ['flight_id' => $flight->id];
        Bid::where($where)->delete();
        $flight->delete();
    }

    /**
     * Update any custom PIREP fields
     */
    public function updateCustomFields(Flight $flight, array $field_values): void
    {
        foreach ($field_values as $fv) {
            FlightFieldValue::updateOrCreate(
                [
                    'flight_id' => $flight->id,
                    'name'      => $fv['name'],
                ],
                [
                    'value' => $fv['value'],
                ]
            );
        }
    }

    /**
     * Return all of the navaid points as a collection
     */
    public function getRoute(Flight $flight): Collection
    {
        if (!$flight->route) {
            return collect();
        }

        $route_points = array_map(strtoupper(...), explode(' ', (string) $flight->route));

        $route = Navdata::whereIn('id', $route_points)->get();

        // Put it back into the original order the route is in
        $return_points = [];
        foreach ($route_points as $rp) {
            $return_points[] = $route->where('id', $rp)->first();
        }

        return collect($return_points);
    }

    public function removeExpiredRepositionFlights(): void
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, Flight> $flights */
        $flights = Flight::where('route_code', PirepStatus::DIVERTED)->get();

        foreach ($flights as $flight) {
            $diverted_pirep = Pirep::with('aircraft')
                ->where([
                    'user_id'        => $flight->user_id,
                    'arr_airport_id' => $flight->dpt_airport_id,
                    'status'         => PirepStatus::DIVERTED,
                    'state'          => PirepState::ACCEPTED,
                ])
                ->orderBy('submitted_at', 'desc')
                ->first();

            $ac = $diverted_pirep?->aircraft;
            if (!$ac || $ac->airport_id != $flight->dpt_airport_id) { // Aircraft has moved or diverted pirep/aircraft no longer exists
                $flight->delete();
            }
        }
    }
}

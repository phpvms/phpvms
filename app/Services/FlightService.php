<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Service;
use App\Exceptions\DuplicateFlight;
use App\Models\Aircraft;
use App\Models\Bid;
use App\Models\Enums\Days;
use App\Models\Enums\PirepState;
use App\Models\Enums\PirepStatus;
use App\Models\Flight;
use App\Models\FlightFieldValue;
use App\Models\Navdata;
use App\Models\Pirep;
use App\Models\Subfleet;
use App\Models\User;
use App\Support\Units\Time;
use Exception;
use Illuminate\Support\Collection;

class FlightService extends Service
{
    public function __construct(
        private readonly AirportService $airportSvc,
        private readonly UserService $userSvc
    ) {}

    /**
     * Create a new flight
     */
    public function createFlight(array $fields): Flight
    {
        $fields = $this->normalizeAirportIds($fields);

        $flightTmp = new Flight($fields);
        if ($this->isFlightDuplicate($flightTmp)) {
            throw new DuplicateFlight($flightTmp);
        }

        $this->airportSvc->lookupAirportIfNotFound($fields['dpt_airport_id']);
        $this->airportSvc->lookupAirportIfNotFound($fields['arr_airport_id']);

        $fields = $this->transformFlightFields($fields);

        return Flight::create($fields);
    }

    /**
     * Update a flight with values from the given fields
     */
    public function updateFlight(Flight $flight, array $fields): Flight
    {
        // Normalize airport IDs the same way createFlight does so the
        // in-memory duplicate check sees normalized values and we don't
        // persist mixed-case IDs.
        $fields = $this->normalizeAirportIds($fields);

        // apply the updates here temporarily, don't save
        // the duplicate check uses the in-memory state
        $flight->fill($fields);

        if ($this->isFlightDuplicate($flight)) {
            throw new DuplicateFlight($flight);
        }

        $fields = $this->transformFlightFields($fields);
        $flight->update($fields);

        return $flight->refresh();
    }

    /**
     * Uppercase departure/arrival airport IDs if present.
     *
     * @param  array<string, mixed> $fields
     * @return array<string, mixed>
     */
    private function normalizeAirportIds(array $fields): array
    {
        if (array_key_exists('dpt_airport_id', $fields) && is_string($fields['dpt_airport_id'])) {
            $fields['dpt_airport_id'] = strtoupper($fields['dpt_airport_id']);
        }

        if (array_key_exists('arr_airport_id', $fields) && is_string($fields['arr_airport_id'])) {
            $fields['arr_airport_id'] = strtoupper($fields['arr_airport_id']);
        }

        return $fields;
    }

    /**
     * Check the fields for a flight and transform them
     *
     * @param  array $fields
     * @return array
     */
    protected function transformFlightFields($fields)
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
     * @return list<int>
     */
    public function getAccessibleFlightIds(User $user): array
    {
        $userSubfleets = $this->userSvc->getAllowableSubfleets($user)->pluck('id')->all();

        $userFlights = Flight::query()
            ->whereHas('subfleets', static function ($query) use ($userSubfleets): void {
                $query->whereIn('subfleets.id', $userSubfleets);
            })
            ->pluck('id')
            ->map(static fn ($flightId): int => (int) $flightId)
            ->all();

        $openFlights = Flight::query()
            ->whereNull('user_id')
            ->doesntHave('subfleets')
            ->pluck('id')
            ->map(static fn ($flightId): int => (int) $flightId)
            ->all();

        return array_values(array_unique(array_merge($userFlights, $openFlights)));
    }

    /**
     * Return the proper subfleets for the given bid
     *
     *
     * @return mixed
     */
    public function getSubfleetsForBid(Bid $bid)
    {
        $sf = Subfleet::with([
            'fares',
            'aircraft' => function ($query) use ($bid) {
                $query->where('id', $bid->aircraft_id);
            }])
            ->where('id', $bid->aircraft->subfleet_id)
            ->get();

        return $sf;
    }

    /**
     * Filter out subfleets to only include aircraft that a user has access to
     *
     *
     * @return mixed
     */
    public function filterSubfleets(User $user, Flight $flight)
    {
        // Eager load some of the relationships needed
        // $flight->load(['flight.subfleets', 'flight.subfleets.aircraft', 'flight.subfleets.fares']);
        $subfleets = $flight->subfleets;

        // If no subfleets assigned and airline subfleets are forced, get airline subfleets
        if ($subfleets->count() === 0 && setting('flights.only_company_aircraft', false)) {
            $subfleets = Subfleet::where(['airline_id' => $flight->airline_id])->get();
        }

        // If no subfleets assigned to a flight get users allowed subfleets
        if ($subfleets->count() === 0) {
            $subfleets = $this->userSvc->getAllowableSubfleets($user);
        }

        // If subfleets are still empty return the flight
        if ($subfleets->count() === 0) {
            return $flight;
        }

        // Only allow aircraft that the user has access to by their rank or type rating
        if (setting('pireps.restrict_aircraft_to_rank', false) || setting('pireps.restrict_aircraft_to_typerating', false)) {
            $allowed_subfleets = $this->userSvc->getAllowableSubfleets($user)->pluck('id');
            $subfleets = $subfleets->filter(function (Subfleet $subfleet, $i) use ($allowed_subfleets) {
                return $allowed_subfleets->contains($subfleet->id);
            });
        }

        /*
         * Only allow aircraft that are at the current departure airport
         */
        $aircraft_at_dpt_airport = setting('pireps.only_aircraft_at_dpt_airport', false);
        $aircraft_not_booked = setting('bids.block_aircraft', false);

        if ($aircraft_at_dpt_airport || $aircraft_not_booked) {
            $subfleets->loadMissing('aircraft');

            foreach ($subfleets as $subfleet) {
                /** @var Subfleet $subfleet */
                // @phpstan-ignore-next-line
                $subfleet->aircraft = $subfleet->aircraft->filter(
                    function ($aircraft, $i) use ($user, $flight, $aircraft_at_dpt_airport, $aircraft_not_booked) {
                        if ($aircraft_at_dpt_airport && $aircraft->airport_id !== $flight->dpt_airport_id) {
                            return false;
                        }

                        if ($aircraft_not_booked && $aircraft->bid && $aircraft->bid->user_id !== $user->id) {
                            return false;
                        }

                        return true;
                    }
                )->sortBy(function (Aircraft $ac, int $_) {
                    return !empty($ac->bid);
                });
            }
        }

        /** @phpstan-ignore-next-line  */
        $flight->subfleets = $subfleets;

        return $flight;
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
        // Stored values may be NULL, '', or an actual scalar; treat empty as
        // equivalent to null so casts that coerce '' to 0 (e.g. integer cast
        // on route_leg) still resolve correctly.
        foreach (['route_code', 'route_leg', 'days'] as $column) {
            $value = $flight->{$column};

            if (in_array($value, [null, '', 0, '0'], true)) {
                $query->where(function ($q) use ($column) {
                    $q->whereNull($column)
                        ->orWhere($column, '')
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
     *
     *
     * @return Collection
     */
    public function getRoute(Flight $flight)
    {
        if (!$flight->route) {
            return collect();
        }

        $route_points = array_map('strtoupper', explode(' ', $flight->route));

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

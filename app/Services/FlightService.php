<?php

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
use App\Models\Subfleet;
use App\Models\User;
use App\Repositories\FlightRepository;
use App\Repositories\NavdataRepository;
use App\Repositories\PirepRepository;
use App\Support\Units\Time;

class FlightService extends Service
{
    /**
     * FlightService constructor.
     *
     * @param AirportService    $airportSvc
     * @param FareService       $fareSvc
     * @param FlightRepository  $flightRepo
     * @param NavdataRepository $navDataRepo
     *
     * @parma PirepRepository   $pirepRepo
     *
     * @param UserService $userSvc
     */
    public function __construct(
        private readonly AirportService $airportSvc,
        private readonly FareService $fareSvc,
        private readonly FlightRepository $flightRepo,
        private readonly NavdataRepository $navDataRepo,
        private readonly PirepRepository $pirepRepo,
        private readonly UserService $userSvc
    ) {
    }

    /**
     * Create a new flight
     *
     * @param array $fields
     *
     * @throws \Prettus\Validator\Exceptions\ValidatorException
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function createFlight($fields)
    {
        $fields['dpt_airport_id'] = strtoupper($fields['dpt_airport_id']);
        $fields['arr_airport_id'] = strtoupper($fields['arr_airport_id']);

        $flightTmp = new Flight($fields);
        if ($this->isFlightDuplicate($flightTmp)) {
            throw new DuplicateFlight($flightTmp);
        }

        $this->airportSvc->lookupAirportIfNotFound($fields['dpt_airport_id']);
        $this->airportSvc->lookupAirportIfNotFound($fields['arr_airport_id']);

        $fields = $this->transformFlightFields($fields);
        $flight = $this->flightRepo->create($fields);

        return $flight;
    }

    /**
     * Update a flight with values from the given fields
     *
     * @param Flight $flight
     * @param array  $fields
     *
     * @throws \Prettus\Validator\Exceptions\ValidatorException
     *
     * @return \App\Models\Flight|mixed
     */
    public function updateFlight($flight, $fields)
    {
        // apply the updates here temporarily, don't save
        // the repo->update() call will actually do it
        $flight->fill($fields);

        if ($this->isFlightDuplicate($flight)) {
            throw new DuplicateFlight($flight);
        }

        $fields = $this->transformFlightFields($fields);
        $flight = $this->flightRepo->update($fields, $flight->id);

        return $flight;
    }

    /**
     * Check the fields for a flight and transform them
     *
     * @param array $fields
     *
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
     * Return the proper subfleets for the given bid
     *
     * @param Bid $bid
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
     * @param User   $user
     * @param Flight $flight
     *
     * @return mixed
     */
    public function filterSubfleets(User $user, Flight $flight)
    {
        // Eager load some of the relationships needed
        //$flight->load(['flight.subfleets', 'flight.subfleets.aircraft', 'flight.subfleets.fares']);

        /** @var \Illuminate\Support\Collection $subfleets */
        $subfleets = $flight->subfleets;

        // If no subfleets assigned to a flight get users allowed subfleets
        if ($subfleets === null || $subfleets->count() === 0) {
            $subfleets = $this->userSvc->getAllowableSubfleets($user);
        }

        // If subfleets are still empty return the flight
        if ($subfleets === null || $subfleets->count() === 0) {
            return $flight;
        }

        /*
         * Only allow aircraft that the user has access to in their rank
         */
        if (setting('pireps.restrict_aircraft_to_rank', false)) {
            $allowed_subfleets = $this->userSvc->getAllowableSubfleets($user)->pluck('id');
            $subfleets = $subfleets->filter(function ($subfleet, $i) use ($allowed_subfleets) {
                if ($allowed_subfleets->contains($subfleet->id)) {
                    return true;
                }

                return false;
            });
        }

        /*
         * Only allow aircraft that are at the current departure airport
         */
        $aircraft_at_dpt_airport = setting('pireps.only_aircraft_at_dpt_airport', false);
        $aircraft_not_booked = setting('bids.block_aircraft', false);

        if ($aircraft_at_dpt_airport || $aircraft_not_booked) {
            foreach ($subfleets as $subfleet) {
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

        $flight->subfleets = $subfleets;

        return $flight;
    }

    /**
     * Check if this flight has a duplicate already
     *
     * @param Flight $flight
     *
     * @return bool
     */
    public function isFlightDuplicate(Flight $flight)
    {
        $where = [
            ['id', '<>', $flight->id],
            'airline_id'    => $flight->airline_id,
            'flight_number' => $flight->flight_number,
        ];

        $found_flights = $this->flightRepo->findWhere($where);
        if ($found_flights->count() === 0) {
            return false;
        }

        // Find within all the flights with the same flight number
        // Return any flights that have the same route code and leg
        // If this list is > 0, then this has a duplicate
        $found_flights = $found_flights->filter(function ($value, $key) use ($flight) {
            return $flight->route_code === $value->route_code
                && $flight->route_leg === $value->route_leg
                && $flight->dpt_airport_id === $value->dpt_airport_id
                && $flight->arr_airport_id === $value->arr_airport_id
                && $flight->days === $value->days;
        });

        return !($found_flights->count() === 0);
    }

    /**
     * Delete a flight, and all the user bids, etc associated with it
     *
     * @param Flight $flight
     *
     * @throws \Exception
     */
    public function deleteFlight(Flight $flight): void
    {
        $where = ['flight_id' => $flight->id];
        Bid::where($where)->delete();
        $flight->delete();
    }

    /**
     * Update any custom PIREP fields
     *
     * @param Flight $flight
     * @param array  $field_values
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
     * @param Flight $flight
     *
     * @return \Illuminate\Support\Collection
     */
    public function getRoute(Flight $flight)
    {
        if (!$flight->route) {
            return collect();
        }

        $route_points = array_map('strtoupper', explode(' ', $flight->route));

        $route = $this->navDataRepo->findWhereIn('id', $route_points);

        // Put it back into the original order the route is in
        $return_points = [];
        foreach ($route_points as $rp) {
            $return_points[] = $route->where('id', $rp)->first();
        }

        return collect($return_points);
    }

    public function removeExpiredRepositionFlights(): void
    {
        $flights = $this->flightRepo->where('route_code', PirepStatus::DIVERTED)->get();

        foreach ($flights as $flight) {
            $diverted_pirep = $this->pirepRepo
                ->with('aircraft')
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

<?php

namespace App\Services;

use App\Contracts\Service;
use App\Events\PirepAccepted;
use App\Events\PirepCancelled;
use App\Events\PirepFiled;
use App\Events\PirepPrefiled;
use App\Events\PirepRejected;
use App\Events\ProcessAward;
use App\Events\UserStatsChanged;
use App\Exceptions\AircraftInvalid;
use App\Exceptions\AircraftNotAtAirport;
use App\Exceptions\AircraftNotAvailable;
use App\Exceptions\AircraftPermissionDenied;
use App\Exceptions\AirportNotFound;
use App\Exceptions\PirepCancelNotAllowed;
use App\Exceptions\PirepError;
use App\Exceptions\UserNotAtAirport;
use App\Models\Acars;
use App\Models\Aircraft;
use App\Models\Enums\AcarsType;
use App\Models\Enums\AircraftState;
use App\Models\Enums\FlightType;
use App\Models\Enums\PirepSource;
use App\Models\Enums\PirepState;
use App\Models\Enums\PirepStatus;
use App\Models\Navdata;
use App\Models\Pirep;
use App\Models\PirepComment;
use App\Models\PirepFare;
use App\Models\PirepFieldValue;
use App\Models\SimBrief;
use App\Models\User;
use App\Notifications\Messages\Broadcast\PirepDiverted;
use App\Repositories\AircraftRepository;
use App\Repositories\AirportRepository;
use App\Repositories\FlightRepository;
use App\Repositories\PirepRepository;
use App\Support\Units\Fuel;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Nwidart\Modules\Facades\Module;

class PirepService extends Service
{
    /**
     * @param AirportRepository  $airportRepo
     * @param AirportService     $airportSvc
     * @param AircraftRepository $aircraftRepo
     * @param FareService        $fareSvc
     * @param FlightRepository   $flightRepo
     * @param GeoService         $geoSvc
     * @param PirepRepository    $pirepRepo
     * @param SimBriefService    $simBriefSvc
     * @param UserService        $userSvc
     */
    public function __construct(
        private readonly AirportRepository $airportRepo,
        private readonly AirportService $airportSvc,
        private readonly AircraftRepository $aircraftRepo,
        private readonly FareService $fareSvc,
        private readonly FlightRepository $flightRepo,
        private readonly GeoService $geoSvc,
        private readonly PirepRepository $pirepRepo,
        private readonly SimBriefService $simBriefSvc,
        private readonly UserService $userSvc
    ) {
    }

    /**
     * Create a prefiled PIREP
     *
     * @param User              $user
     * @param array             $attrs
     * @param PirepFieldValue[] $fields
     * @param PirepFare[]       $fares
     *
     * @throws \Exception
     * @throws AirportNotFound If one of the departure or arrival airports isn't found locally
     *
     * @return \App\Models\Pirep
     */
    public function prefile(User $user, array $attrs, array $fields = [], array $fares = []): Pirep
    {
        $attrs['user_id'] = $user->id;
        $attrs['state'] = PirepState::IN_PROGRESS;

        if (!array_key_exists('status', $attrs)) {
            $attrs['status'] = PirepStatus::INITIATED;
        }

        // Default to a scheduled passenger flight
        if (!array_key_exists('flight_type', $attrs)) {
            $attrs['flight_type'] = FlightType::SCHED_PAX;
        }

        $pirep = new Pirep($attrs);

        // Check if the airports listed actually exist or not. If they're not in the local DB
        // throw an error which should bubble up to say that they don't
        if (setting('general.allow_unadded_airports', false) === true) {
            $this->airportSvc->lookupAirportIfNotFound($pirep->dpt_airport_id);
            $this->airportSvc->lookupAirportIfNotFound($pirep->arr_airport_id);
        } else {
            $dptApt = $this->airportRepo->findWithoutFail($pirep->dpt_airport_id);
            if (!$dptApt) {
                throw new AirportNotFound($pirep->dpt_airport_id);
            }

            $arrApt = $this->airportRepo->findWithoutFail($pirep->arr_airport_id);
            if (!$arrApt) {
                throw new AirportNotFound($pirep->arr_airport_id);
            }
        }

        // See if this user is at the current airport
        /* @noinspection NotOptimalIfConditionsInspection */
        if (setting('pilots.only_flights_from_current', false)
            && $user->curr_airport_id !== $pirep->dpt_airport_id) {
            throw new UserNotAtAirport($user, $pirep->dpt_airport);
        }

        // See if this user is allowed to fly this aircraft
        if (setting('pireps.restrict_aircraft_to_rank', false)
            && !$this->userSvc->aircraftAllowed($user, $pirep->aircraft_id)) {
            throw new AircraftPermissionDenied($user, $pirep->aircraft);
        }

        // See if this aircraft is valid
        /** @var Aircraft $aircraft */
        $aircraft = $this->aircraftRepo->findWithoutFail($pirep->aircraft_id);
        if ($aircraft === null) {
            throw new AircraftInvalid($aircraft);
        }

        // See if this aircraft is available for flight
        /** @var Aircraft $aircraft */
        $aircraft = $this->aircraftRepo->where('id', $pirep->aircraft_id)->where('state', AircraftState::PARKED)->first();
        if ($aircraft === null) {
            throw new AircraftNotAvailable($pirep->aircraft);
        }

        // See if this aircraft is being used by another user's active simbrief ofp
        if (setting('simbrief.block_aircraft', false)) {
            $sb_aircraft = SimBrief::select('aircraft_id')
                ->where('aircraft_id', $pirep->aircraft_id)
                ->where('user_id', '!=', $pirep->user_id)
                ->whereNotNull('flight_id')
                ->count();
            if ($sb_aircraft > 0) {
                throw new AircraftNotAvailable($pirep->aircraft);
            }
        }

        // See if this aircraft is at the departure airport
        /* @noinspection NotOptimalIfConditionsInspection */
        if (setting('pireps.only_aircraft_at_dpt_airport') && $aircraft->airport_id !== $pirep->dpt_airport_id) {
            throw new AircraftNotAtAirport($pirep->aircraft);
        }

        // Find if there's a duplicate, if so, let's work on that
        $dupe_pirep = $this->findDuplicate($pirep);
        if ($dupe_pirep !== false) {
            $pirep = $dupe_pirep;
            Log::info('Found duplicate PIREP, id='.$dupe_pirep->id);
            if ($pirep->cancelled) {
                throw new \App\Exceptions\PirepCancelled($pirep);
            }
        }

        $pirep->status = PirepStatus::INITIATED;
        $pirep->save();
        $pirep->refresh();

        // Check if there is a simbrief_id, update it to have the pirep_id
        // Keep the flight_id until the end of flight (pirep file)
        if (array_key_exists('simbrief_id', $attrs)) {
            /** @var SimBrief $simbrief */
            $simbrief = SimBrief::find($attrs['simbrief_id']);
            if ($simbrief) {
                $this->simBriefSvc->attachSimbriefToPirep($pirep, $simbrief, true);
            }
        }

        $this->updateCustomFields($pirep->id, $fields);
        $this->fareSvc->saveToPirep($pirep, $fares);

        event(new PirepPrefiled($pirep));

        return $pirep;
    }

    /**
     * Create a new PIREP with some given fields
     *
     * @param Pirep $pirep
     * @param array PirepFieldValue[] $field_values
     *
     * @return Pirep
     */
    public function create(Pirep $pirep, array $fields = []): Pirep
    {
        if (empty($fields)) {
            $fields = [];
        }

        // Check the block times. If a block on (arrival) time isn't
        // specified, then use the time that it was submitted. It won't
        // be the most accurate, but that might be OK
        if (!$pirep->block_on_time) {
            if ($pirep->submitted_at) {
                $pirep->block_on_time = $pirep->submitted_at;
            } else {
                $pirep->block_on_time = Carbon::now('UTC');
            }
        }

        // If the depart time isn't set, then try to calculate it by
        // subtracting the flight time from the block_on (arrival) time
        if (!$pirep->block_off_time && $pirep->flight_time > 0) {
            $pirep->block_off_time = $pirep->block_on_time->subMinutes($pirep->flight_time);
        }

        // Check that there's a submit time
        if (!$pirep->submitted_at) {
            $pirep->submitted_at = Carbon::now('UTC');
        }

        $pirep->status = PirepStatus::ARRIVED;

        // Copy some fields over from Flight/SimBrief if we have it
        if ($pirep->flight) {
            $pirep->planned_distance = isset($pirep->flight->simbrief) ? $pirep->flight->simbrief->xml->general->air_distance : $pirep->flight->distance;
            $pirep->planned_flight_time = $pirep->flight->flight_time;
        }

        $pirep->save();
        $pirep->refresh();

        $this->updateCustomFields($pirep->id, $fields);

        return $pirep;
    }

    /**
     * @param PirepFieldValue[] $fields
     * @param PirepFare[]       $fares
     *
     * @throws \Prettus\Validator\Exceptions\ValidatorException
     * @throws \Exception
     */
    public function update(string $pirep_id, array $attrs, array $fields = [], array $fares = []): Pirep
    {
        $pirep = $this->pirepRepo->update($attrs, $pirep_id);
        $this->updateCustomFields($pirep_id, $fields);
        $this->fareSvc->saveToPirep($pirep, $fares);

        return $pirep;
    }

    /**
     * Finalize a PIREP (meaning it's been filed)
     *
     * @param Pirep             $pirep
     * @param array             $attrs
     * @param PirepFieldValue[] $fields
     * @param PirepFare[]       $fares
     *
     * @throws \Exception
     *
     * @return Pirep
     */
    public function file(Pirep $pirep, array $attrs = [], array $fields = [], array $fares = []): Pirep
    {
        if (empty($fields)) {
            $fields = [];
        }

        // Check if the PIREP has already been submitted
        $is_already_submitted = in_array($pirep->state, [
            PirepState::PENDING,
            PirepState::ACCEPTED,
            PirepState::CANCELLED,
            PirepState::REJECTED,
        ], true);

        if ($is_already_submitted) {
            throw new PirepError($pirep, 'PIREP has already been submitted');
        }

        $attrs['state'] = PirepState::PENDING;
        $attrs['status'] = PirepStatus::ARRIVED;
        $attrs['submitted_at'] = Carbon::now('UTC');

        $this->pirepRepo->update($attrs, $pirep->id);
        $pirep->refresh();

        // Check if there is a simbrief_id, change it to be set to the PIREP
        // at the end of the flight when it's been filed
        if (array_key_exists('simbrief_id', $attrs)) {
            /** @var SimBrief $simbrief */
            $simbrief = SimBrief::find($attrs['simbrief_id']);
            if ($simbrief) {
                $this->simBriefSvc->attachSimbriefToPirep($pirep, $simbrief);
            }
        }

        // Check the block times. If a block on (arrival) time isn't
        // specified, then use the time that it was submitted. It won't
        // be the most accurate, but that might be OK
        if (!$pirep->block_on_time) {
            if ($pirep->submitted_at) {
                $pirep->block_on_time = $pirep->submitted_at;
            } else {
                $pirep->block_on_time = Carbon::now('UTC');
            }
        }

        // Check that there's a submit time
        if (!$pirep->submitted_at) {
            $pirep->submitted_at = Carbon::now('UTC');
        }

        // Copy some fields over from Flight/SimBrief if we have it
        if ($pirep->flight) {
            $pirep->planned_distance = isset($pirep->simbrief) ? $pirep->simbrief->xml->general->air_distance : $pirep->flight->distance;
            $pirep->planned_flight_time = $pirep->flight->flight_time;
        }

        $pirep->save();
        $pirep->refresh();

        $this->updateCustomFields($pirep->id, $fields);
        $this->fareSvc->saveToPirep($pirep, $fares);

        return $pirep;
    }

    /**
     * Find if there are duplicates to a given PIREP. Ideally, the passed
     * in PIREP hasn't been saved or gone through the create() method
     *
     * @param Pirep $pirep
     *
     * @return bool|Pirep
     */
    public function findDuplicate(Pirep $pirep)
    {
        $minutes = setting('pireps.duplicate_check_time', 10);
        $time_limit = Carbon::now('UTC')->subMinutes($minutes)->toDateTimeString();

        $where = [
            'user_id'        => $pirep->user_id,
            'airline_id'     => $pirep->airline_id,
            'flight_number'  => $pirep->flight_number,
            'dpt_airport_id' => $pirep->dpt_airport_id,
            'arr_airport_id' => $pirep->arr_airport_id,
        ];

        if (filled($pirep->route_code)) {
            $where['route_code'] = $pirep->route_code;
        }

        if (filled($pirep->route_leg)) {
            $where['route_leg'] = $pirep->route_leg;
        }

        try {
            $found_pireps = Pirep::where($where)
                ->where('state', '!=', PirepState::CANCELLED)
                ->where('created_at', '>=', $time_limit)
                ->get();

            if ($found_pireps->count() === 0) {
                return false;
            }

            return $found_pireps[0];
        } catch (ModelNotFoundException $e) {
            return false;
        }
    }

    /**
     * Save the route into the ACARS table with AcarsType::ROUTE
     * This attempts to create the route from the navdata and the route
     * entered into the PIREP's route field
     *
     * @param Pirep $pirep
     *
     * @throws \Exception
     *
     * @return Pirep
     */
    public function saveRoute(Pirep $pirep): Pirep
    {
        // Delete all the existing nav points
        Acars::where([
            'pirep_id' => $pirep->id,
            'type'     => AcarsType::ROUTE,
        ])->delete();

        // See if a route exists
        if (!filled($pirep->route)) {
            return $pirep;
        }

        if (!filled($pirep->dpt_airport)) {
            Log::error('saveRoute: dpt_airport not found: '.$pirep->dpt_airport_id);
            return $pirep;
        }

        $route = $this->geoSvc->getCoordsFromRoute(
            $pirep->dpt_airport_id,
            $pirep->arr_airport_id,
            [$pirep->dpt_airport->lat, $pirep->dpt_airport->lon],
            $pirep->route
        );

        /**
         * @var $point Navdata
         */
        $point_count = 1;
        foreach ($route as $point) {
            $acars = new Acars();
            $acars->pirep_id = $pirep->id;
            $acars->type = AcarsType::ROUTE;
            $acars->nav_type = $point->type;
            $acars->order = $point_count;
            $acars->name = $point->id;
            $acars->lat = $point->lat;
            $acars->lon = $point->lon;

            $acars->save();
            $point_count++;
        }

        return $pirep;
    }

    /**
     * Submit the PIREP. Figure out its default state
     *
     * @param Pirep $pirep
     *
     * @throws \Exception
     */
    public function submit(Pirep $pirep)
    {
        // Check if there is a simbrief_id, change it to be set to the PIREP
        // at the end of the flight when it's been submitted finally.
        // Prefile, Save (as draft) and File already have this but the Submit button
        // visible at pireps.show blade uses this function so Simbrief also needs to
        // checked here too (to remove the flight_id and release the aircraft)
        if (!empty($pirep->simbrief)) {
            /** @var SimBrief $simbrief */
            $simbrief = SimBrief::find($pirep->simbrief->id);
            if ($simbrief) {
                $this->simBriefSvc->attachSimbriefToPirep($pirep, $simbrief);
            }
        }

        Log::info('New PIREP filed, pirep_id: '.$pirep->id);
        event(new PirepFiled($pirep));

        $pirep->refresh();

        // Figure out what pirep state should be, if nothing provided yet.
        if ($pirep->state != PirepState::ACCEPTED && $pirep->state != PirepState::REJECTED) {
            $default_state = PirepState::PENDING;
        } else {
            $default_state = $pirep->state;
        }

        // If pirep is still at PENDING or DRAFT state decide the default behavior by looking at rank settings
        if ($pirep->state === PirepState::PENDING || $pirep->state === PirepState::DRAFT) {
            if ($pirep->source === PirepSource::ACARS && $pirep->user->rank->auto_approve_acars) {
                $default_state = PirepState::ACCEPTED;
            } elseif ($pirep->source === PirepSource::MANUAL && $pirep->user->rank->auto_approve_manual) {
                $default_state = PirepState::ACCEPTED;
            }
        }

        // only update the pilot last state if they are accepted
        if ($default_state === PirepState::ACCEPTED) {
            $pirep = $this->accept($pirep);
        } elseif ($default_state === PirepState::REJECTED) {
            $pirep = $this->reject($pirep);
        } else {
            $pirep->state = $default_state;
        }

        $pirep->save();
    }

    /**
     * Cancel a PIREP
     *
     * @param Pirep $pirep
     *
     * @throws \Prettus\Validator\Exceptions\ValidatorException
     *
     * @return Pirep
     */
    public function cancel(Pirep $pirep): Pirep
    {
        if (in_array($pirep->state, Pirep::$cancel_states, true)) {
            Log::info('PIREP '.$pirep->id.' can\'t be cancelled, state='.$pirep->state);

            throw new PirepCancelNotAllowed($pirep);
        }

        $pirep = $this->pirepRepo->update([
            'state'  => PirepState::CANCELLED,
            'status' => PirepStatus::CANCELLED,
        ], $pirep->id);

        event(new PirepCancelled($pirep));

        return $pirep;
    }

    /**
     * Delete the PIREP and all of the associated data. Does a force delete to make sure that we
     * don't run into problems with foreign keys. Models/tables affected:
     *
     * acars
     * bids
     * pirep_comments
     * pirep_fares
     * pirep_field_values
     * simbrief
     *
     * @param Pirep $pirep
     */
    public function delete(Pirep $pirep): void
    {
        $user_id = $pirep->user_id;

        $w = ['pirep_id' => $pirep->id];
        PirepComment::where($w)->forceDelete();
        PirepFare::where($w)->forceDelete();
        PirepFieldValue::where($w)->forceDelete();
        SimBrief::where($w)->forceDelete();
        $pirep->forceDelete();

        // Update the user's last PIREP
        $last_pirep = Pirep::where(['user_id' => $user_id, 'state' => PirepState::ACCEPTED])
            ->latest('submitted_at')
            ->first();

        $user = User::find($user_id);
        $user->last_pirep_id = !empty($last_pirep) ? $last_pirep->id : null;
        $user->save();
    }

    /**
     * Update any custom PIREP fields
     *
     * @param string            $pirep_id
     * @param PirepFieldValue[] $field_values
     */
    public function updateCustomFields(string $pirep_id, array $field_values): void
    {
        if (!$field_values || empty($field_values)) {
            return;
        }

        foreach ($field_values as $fv) {
            PirepFieldValue::updateOrCreate(
                ['pirep_id' => $pirep_id, 'name' => $fv['name']],
                ['value' => $fv['value'], 'source' => $fv['source']]
            );
        }
    }

    /**
     * @param Pirep $pirep
     * @param int   $new_state
     *
     * @throws \Exception
     *
     * @return Pirep
     */
    public function changeState(Pirep $pirep, int $new_state): Pirep
    {
        Log::info('PIREP '.$pirep->id.' state change from '.$pirep->state.' to '.$new_state);

        if ($pirep->state === $new_state) {
            return $pirep;
        }

        /*
         * Move from a PENDING status into either ACCEPTED or REJECTED
         */
        if ($pirep->state === PirepState::PENDING) {
            if ($new_state === PirepState::ACCEPTED) {
                return $this->accept($pirep);
            }

            if ($new_state === PirepState::REJECTED) {
                return $this->reject($pirep);
            }

            return $pirep;
        }

        /*
         * Move from a ACCEPTED to REJECTED status
         */
        if ($pirep->state === PirepState::ACCEPTED) {
            $pirep = $this->reject($pirep);
            return $pirep;
        }

        /*
         * Move from REJECTED to ACCEPTED
         */
        if ($pirep->state === PirepState::REJECTED) {
            $pirep = $this->accept($pirep);
            return $pirep;
        }

        return $pirep->refresh();
    }

    /**
     * @param Pirep $pirep
     *
     * @throws \Exception
     *
     * @return Pirep
     */
    public function accept(Pirep $pirep): Pirep
    {
        // moving from a REJECTED state to ACCEPTED, reconcile statuses
        if ($pirep->state === PirepState::ACCEPTED) {
            return $pirep;
        }

        $ft = $pirep->flight_time;
        $pilot = $pirep->user;

        $this->userSvc->adjustFlightTime($pilot, $ft);
        $this->userSvc->adjustFlightCount($pilot, +1);
        $this->userSvc->calculatePilotRank($pilot);
        $pirep->user->refresh();

        // Change the status
        $pirep->state = PirepState::ACCEPTED;
        $pirep->save();
        $pirep->refresh();

        Log::info('PIREP '.$pirep->id.' state change to ACCEPTED');

        $fuel_remain = $pirep->block_fuel->internal() - $pirep->fuel_used->internal();
        $fuel_on_board = Fuel::make($fuel_remain, config('phpvms.internal_units.fuel'));

        // Update the aircraft
        $pirep->aircraft->flight_time = $pirep->aircraft->flight_time + $pirep->flight_time;
        $pirep->aircraft->airport_id = $pirep->arr_airport_id;
        $pirep->aircraft->landing_time = $pirep->updated_at;
        $pirep->aircraft->fuel_onboard = $fuel_on_board;
        $pirep->aircraft->save();

        $pirep->refresh();

        $this->setPilotState($pilot, $pirep);
        event(new PirepAccepted($pirep));
        event(new ProcessAward($pirep->user));

        return $pirep;
    }

    /**
     * @param Pirep $pirep
     *
     * @return Pirep
     */
    public function reject(Pirep $pirep): Pirep
    {
        // If this was previously ACCEPTED, then reconcile the flight hours
        // that have already been counted, etc
        if ($pirep->state === PirepState::ACCEPTED) {
            $user = $pirep->user;
            $ft = $pirep->flight_time * -1;

            $this->userSvc->adjustFlightTime($user, $ft);
            $this->userSvc->adjustFlightCount($user, -1);
            $this->userSvc->calculatePilotRank($user);
            $pirep->user->refresh();

            $pirep->aircraft->flight_time -= $pirep->flight_time;
            $pirep->aircraft->save();
        }

        // Change the status
        $pirep->state = PirepState::REJECTED;
        $pirep->save();
        $pirep->refresh();

        Log::info('PIREP '.$pirep->id.' state change to REJECTED');

        event(new PirepRejected($pirep));

        return $pirep;
    }

    /**
     * @param User  $pilot
     * @param Pirep $pirep
     */
    public function setPilotState(User $pilot, Pirep $pirep)
    {
        $pilot->refresh();

        $previous_airport = $pilot->curr_airport_id;
        $pilot->curr_airport_id = $pirep->arr_airport_id;
        $pilot->last_pirep_id = $pirep->id;
        $pilot->save();

        $pirep->refresh();

        event(new UserStatsChanged($pilot, 'airport', $previous_airport));
    }

    public function handleDiversion(Pirep $pirep): void
    {
        // Return if diversion handling is disabled
        if (!setting('pireps.handle_diversion', false)) {
            return;
        }

        $diversion_airport_id = $pirep->fields->where('slug', 'diversion-airport')->first()?->value;

        // Return if no diversion
        if (!$diversion_airport_id) {
            return;
        }

        $diversion_airport = $this->airportRepo->findWithoutFail($diversion_airport_id);

        // Return if diversion airport not found and airport lookup is disabled
        if (!$diversion_airport && !setting('general.auto_airport_lookup', false)) {
            return;
        }

        if (!$diversion_airport) {
            $diversion_airport = $this->airportSvc->lookupAirportIfNotFound($diversion_airport_id);
        }

        // Return if we still not have any diversion airport
        if (!$diversion_airport) {
            return;
        }

        $pirep->loadMissing('aircraft', 'flight', 'user');
        $aircraft = $pirep->aircraft;
        $flight = $pirep->flight;
        $user = $pirep->user;

        event(new PirepDiverted($pirep));

        $has_vmsacars = Module::find('VMSAcars');

        if ($has_vmsacars && $flight) {
            $free_flights_disabled = DB::table('vmsacars_config')->find('disable_free_flights')?->value;
            // Log::debug('vmsAcars | Disable Free Flights Setting: '.$free_flights_disabled.', considered as '.get_truth_state($free_flights_disabled));

            if (get_truth_state($free_flights_disabled) == true) {
                // Lookup for flights from diversion airport to original destination airport
                $reposition_flights_count = $this->flightRepo->where([
                    'dpt_airport_id' => $diversion_airport->id,
                    'arr_airport_id' => $pirep->arr_airport_id,
                    'airline_id'     => $pirep->airline_id,
                ])->whereHas('subfleets', function ($query) use ($aircraft) {
                    $query->where('subfleet_id', $aircraft->subfleet_id);
                })->count();

                // Create a reposition flight if there is no flight
                if ($reposition_flights_count == 0) {
                    $reposition_flight = $this->flightRepo->create([
                        'airline_id'     => $flight->airline_id,
                        'flight_number'  => $flight->flight_number,
                        'callsign'       => $flight->callsign,
                        'route_code'     => PirepStatus::DIVERTED,
                        'dpt_airport_id' => $diversion_airport->id,
                        'arr_airport_id' => $pirep->arr_airport_id,
                        'distance'       => $this->airportSvc->calculateDistance($diversion_airport->id, $pirep->arr_airport_id),
                        'flight_time'    => 1,
                        'flight_type'    => $flight->flight_type,
                        'notes'          => 'DIVERTED FLIGHT RE-POSITIONING TO DESTINATION',
                        'visible'        => true,
                        'active'         => true,
                        'user_id'        => $user->id,
                    ]);

                    $reposition_flight->subfleets()->syncWithoutDetaching([$aircraft->subfleet_id]);

                    Log::info('Diversion repositioning flight '.$reposition_flight->id.' from '.$diversion_airport->id.' to '.$pirep->arr_airport_id.' created');
                } else {
                    Log::info('Diversion repositioning flight NOT created, '.$reposition_flights_count.' flights found between '.$diversion_airport->id.' and '.$pirep->arr_airport_id);
                }
            }
        }

        if (setting('notifications.discord_pirep_diverted', false)) {
            Notification::send([$pirep], new PirepDiverted($pirep));
        }

        // Update aircraft position
        $aircraft->update(['airport_id' => $diversion_airport->id]);

        // Update user position
        $user->update(['curr_airport_id' => $diversion_airport->id]);

        // Update pirep details
        $pirep->update([
            'notes'          => 'DIVERTED FROM '.$pirep->arr_airport_id.' TO '.$diversion_airport->id.' '.$pirep->notes,
            'alt_airport_id' => $pirep->arr_airport_id, // Save intended dest as alternate for fixing it back when needed
            'arr_airport_id' => $diversion_airport->id, // Use diversion dest as the new arrival
            'flight_id'      => null, // Remove the flight id to drop the relationship
            'route_leg'      => null, // Remove the route_leg to exclude this pirep from tour checks
        ]);

        Log::info('Pirep '.$pirep->id.' Flight '.$pirep->ident.' DIVERTED to '.$diversion_airport->id.', assets MOVED to Diversion Airport');
    }
}

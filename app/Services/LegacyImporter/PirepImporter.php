<?php

namespace App\Services\LegacyImporter;

use App\Models\Enums\FlightType;
use App\Models\Enums\PirepSource;
use App\Models\Enums\PirepState;
use App\Models\Enums\PirepStatus;
use App\Models\Pirep;
use App\Services\FinanceService;
use App\Support\Money;

class PirepImporter extends BaseImporter
{
    protected $table = 'pireps';

    protected $idField = 'pirepid';

    public function run($start = 0)
    {
        $this->comment('--- PIREP IMPORT ---');

        /** @var FinanceService $financeSvc */
        $financeSvc = app(FinanceService::class);

        $fields = [
            'pirepid',
            'pilotid',
            'code',
            'aircraft',
            'flightnum',
            'depicao',
            'arricao',
            'fuelused',
            'route',
            'source',
            'accepted',
            'submitdate',
            'distance',
            'landingrate',
            'flighttime_stamp',
            'flighttype',
            'fuelprice',
            'expenses',
            'gross',
            'pilotpay',
        ];

        // See if there's a flightlevel column, export that if there is
        $columns = $this->db->getColumns($this->table);
        if (in_array('flightlevel', $columns, true)) {
            $fields[] = 'flightlevel';
        }

        $count = 0;
        $rows = $this->db->readRows($this->table, $this->idField, $start, $fields);
        foreach ($rows as $row) {
            $pirep_id = $row->pirepid;
            $user_id = $this->idMapper->getMapping('users', $row->pilotid);
            $airline_id = $this->idMapper->getMapping('airlines', $row->code);
            $aircraft_id = $this->idMapper->getMapping('aircraft', $row->aircraft);

            $attrs = [
                'user_id'        => $user_id,
                'airline_id'     => $airline_id,
                'aircraft_id'    => $aircraft_id,
                'flight_number'  => $row->flightnum ?: '',
                'dpt_airport_id' => $row->depicao,
                'arr_airport_id' => $row->arricao,
                'block_fuel'     => $row->fuelused,
                'fuel_used'      => $row->fuelused,
                'landing_rate'   => $row->landingrate,
                'route'          => $row->route ?: '',
                'source_name'    => $row->source,
                'state'          => $this->mapState($row->accepted),
                'status'         => PirepStatus::ARRIVED,
                'submitted_at'   => $this->parseDate($row->submitdate),
                'created_at'     => $this->parseDate($row->submitdate),
                'updated_at'     => $this->parseDate($row->submitdate),
            ];

            // Set the distance
            $distance = round($row->distance ?: 0, 2);
            $attrs['distance'] = $distance;
            $attrs['planned_distance'] = $distance;

            // Set the flight time properly
            $duration = $this->convertDuration($row->flighttime_stamp);
            $attrs['flight_time'] = $duration;
            $attrs['planned_flight_time'] = $duration;

            // Set how it was filed
            if (strtoupper($row->source) === 'MANUAL') {
                $attrs['source'] = PirepSource::MANUAL;
            } else {
                $attrs['source'] = PirepSource::ACARS;
            }

            // Set the flight type
            $row->flighttype = strtoupper($row->flighttype);
            if ($row->flighttype === 'P') {
                $attrs['flight_type'] = FlightType::SCHED_PAX;
            } elseif ($row->flighttype === 'C') {
                $attrs['flight_type'] = FlightType::SCHED_CARGO;
            } else {
                $attrs['flight_type'] = FlightType::CHARTER_PAX_ONLY;
            }

            // Set the flight level of the PIREP is set
            if (property_exists($row, 'flightlevel')) {
                $attrs['level'] = $row->flightlevel;
            } else {
                $attrs['level'] = 0;
            }

            $w = ['id' => $pirep_id];

            $pirep = Pirep::updateOrCreate($w, $attrs);
            // $pirep = Pirep::create(array_merge($w, $attrs));

            // Log::debug('pirep oldid='.$pirep_id.', olduserid='.$row->pilotid
            //    .'; new id='.$pirep->id.', user id='.$user_id);

            $source = strtoupper($row->source);
            if ($source === 'SMARTCARS') {
                // TODO: Parse smartcars log into the acars table
            } elseif ($source === 'KACARS') {
                // TODO: Parse kACARS log into acars table
            } elseif ($source === 'XACARS') {
                // TODO: Parse XACARS log into acars table
            }

            // TODO: Add extra fields in as PIREP fields
            $this->idMapper->addMapping('pireps', $row->pirepid, $pirep->id);

            if ($pirep->wasRecentlyCreated) {
                $count++;
            }

            if ($pirep->user && $pirep->state === PirepState::ACCEPTED) {
                $pirep->user->update([
                    'last_pirep_id' => $pirep->id,
                ]);
            }

            if (!$pirep->airline || !$pirep->airline->journal) {
                continue;
            }

            // Some financial imports
            if ($row->fuelprice && $row->fuelprice != 0) {
                $fuel_price = Money::createFromAmount($row->fuelprice);
                $financeSvc->debitFromJournal(
                    $pirep->airline->journal,
                    $fuel_price,
                    $pirep,
                    'Fuel Cost',
                    'Fuel',
                    'fuel'
                );
            }

            if ($row->expenses && $row->expenses != 0) {
                $expenses_price = Money::createFromAmount($row->expenses);
                $financeSvc->debitFromJournal(
                    $pirep->airline->journal,
                    $expenses_price,
                    $pirep,
                    'Expenses',
                    'Expenses',
                    'expense'
                );
            }

            if ($row->gross && $row->gross != 0) {
                $gross_revenue = Money::createFromAmount($row->gross);
                $financeSvc->creditToJournal(
                    $pirep->airline->journal,
                    $gross_revenue,
                    $pirep,
                    'Incomes',
                    'Fares',
                    'fare'
                );
            }

            if ($row->pilotpay && $row->pilotpay != 0) {
                $pilot_pay = Money::createFromAmount($row->pilotpay * $duration / 60);
                $financeSvc->debitFromJournal(
                    $pirep->airline->journal,
                    $pilot_pay,
                    $pirep,
                    'Pilot payment',
                    'Pilot Pay',
                    'pilot_pay'
                );

                $financeSvc->creditToJournal(
                    $pirep->user->journal,
                    $pilot_pay,
                    $pirep,
                    'Pilot payment',
                    'Pilot Pay',
                    'pilot_pay'
                );
            }
        }

        $this->info('Imported '.$count.' pireps');
    }

    /**
     * Map the old state to the current
     * https://github.com/nabeelio/phpvms_v2/blob/master/core/app.config.php#L450.
     *
     * @param  int $old_state
     * @return int
     */
    private function mapState($old_state)
    {
        $map = [
            0 => PirepState::PENDING,
            1 => PirepState::ACCEPTED,
            2 => PirepState::REJECTED,
            3 => PirepState::IN_PROGRESS,
        ];

        $old_state = (int) $old_state;
        if (!in_array($old_state, $map, true)) {
            return PirepState::PENDING;
        }

        return $map[$old_state];
    }
}

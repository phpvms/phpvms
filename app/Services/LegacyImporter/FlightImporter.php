<?php

declare(strict_types=1);

namespace App\Services\LegacyImporter;

use App\Models\Flight;
use App\Support\FlightTimeParser;
use Exception;

class FlightImporter extends BaseImporter
{
    protected $table = 'schedules';

    public function run($start = 0): void
    {
        $this->comment('--- FLIGHT SCHEDULE IMPORT ---');

        $fields = [
            'id',
            'code',
            'flightnum',
            'depicao',
            'arricao',
            'route',
            'distance',
            'flightlevel',
            'deptime',
            'arrtime',
            'flighttime',
            'notes',
            'enabled',
        ];

        $count = 0;
        $rows = $this->db->readRows($this->table, $this->idField, $start, $fields);
        foreach ($rows as $row) {
            $airline_id = $this->idMapper->getMapping('airlines', $row->code);

            $flight_num = trim((string) $row->flightnum);

            $attrs = [
                'dpt_airport_id' => $row->depicao,
                'arr_airport_id' => $row->arricao,
                'route'          => $row->route ?: '',
                'distance'       => round($row->distance ?: 0, 2),
                'level'          => $row->flightlevel ?: 0,
                'departure_time' => filled($row->deptime) ? FlightTimeParser::parse((string) $row->deptime) : null,
                'arrival_time'   => filled($row->arrtime) ? FlightTimeParser::parse((string) $row->arrtime) : null,
                'flight_time'    => $this->convertDuration($row->flighttime) ?: '',
                'notes'          => $row->notes ?: '',
                'enabled'        => $row->enabled ?: true,
            ];

            try {
                $w = ['airline_id' => $airline_id, 'flight_number' => $flight_num];
                // $flight = Flight::updateOrCreate($w, $attrs);
                $flight = Flight::create(array_merge($w, $attrs));
            } catch (Exception $e) {
                $this->error($e->getMessage());

                continue;
            }

            $this->idMapper->addMapping('flights', $row->id, $flight->id);

            // TODO: deserialize route_details into ACARS table

            if ($flight->wasRecentlyCreated) {
                $count++;
            }
        }

        $this->info('Imported '.$count.' flights');
    }
}

<?php

namespace App\Services\LegacyImporter;

use App\Models\Aircraft;
use App\Models\Airline;
use App\Models\Subfleet;

class AircraftImporter extends BaseImporter
{
    protected $table = 'aircraft';

    public function run($start = 0)
    {
        $this->comment('--- AIRCRAFT IMPORT ---');

        $fields = [
            'id',
            'icao',
            'name',
            'fullname',
            'registration',
            'enabled',
        ];

        // See if there is an airline column
        $columns = $this->db->getColumns($this->table);
        if (in_array('airline', $columns, true)) {
            $fields[] = 'airline';
        }

        if (in_array('location', $columns, true)) {
            $fields[] = 'location';
        }

        $count = 0;
        $rows = $this->db->readRows($this->table, $this->idField, $start, $fields);
        foreach ($rows as $row) {
            $subfleet_name = $row->icao;

            $airline_id = null;
            if (!empty($row->airline)) {
                $subfleet_name = $row->airline.' - '.$row->icao;
                $airline_id = $this->idMapper->getMapping('airlines', $row->airline);
            }

            $subfleet = $this->getSubfleet($subfleet_name, $row->icao, $airline_id);

            $where = [
                'registration' => $row->registration,
            ];

            $cols = [
                'icao'        => $row->icao,
                'name'        => $row->fullname,
                'subfleet_id' => $subfleet->id,
                'active'      => $row->enabled,
            ];

            if (!empty($row->location)) {
                $cols['airport_id'] = $row->location;
            }

            $aircraft = Aircraft::firstOrCreate($where, $cols);

            $this->idMapper->addMapping('aircraft', $row->id, $aircraft->id);

            if ($aircraft->wasRecentlyCreated) {
                $count++;
            }
        }

        $this->info('Imported '.$count.' aircraft');
    }

    /**
     * Return the subfleet
     *
     * @param  string $name
     * @param  string $icao       ICAO of the subfleet
     * @param  int    $airline_id
     * @return mixed
     */
    protected function getSubfleet($name, $icao, $airline_id = null)
    {
        if (empty($airline_id)) {
            $airline = Airline::first();
            $airline_id = $airline->id;
        }

        return Subfleet::firstOrCreate([
            'airline_id' => $airline_id,
            'name'       => $name,
        ], ['type' => $icao]);
    }
}

<?php

namespace App\Services\LegacyImporter;

use App\Models\Airline;
use Illuminate\Support\Facades\Log;

class AirlineImporter extends BaseImporter
{
    public $table = 'airlines';

    /**
     * @param int $start
     */
    public function run($start = 0)
    {
        $this->comment('--- AIRLINE IMPORT ---');

        $count = 0;
        $rows = $this->db->readRows($this->table, $this->idField, $start);
        foreach ($rows as $row) {
            $attrs = [
                'iata'   => $row->code,
                'name'   => $row->name,
                'active' => $row->enabled,
            ];

            $w = ['icao' => $row->code];

            // $airline = Airline::firstOrCreate($w, $attrs);
            $airline = Airline::create(array_merge($w, $attrs));

            $this->idMapper->addMapping('airlines', $row->id, $airline->id);
            $this->idMapper->addMapping('airlines', $row->code, $airline->id);

            Log::debug('Mapping '.$row->id.'/'.$row->code.' to ID '.$airline->id);

            if ($airline->wasRecentlyCreated) {
                $count++;
            }
        }

        $this->info('Imported '.$count.' airlines');
    }
}

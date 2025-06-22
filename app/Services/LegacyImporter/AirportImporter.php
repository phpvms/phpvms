<?php

namespace App\Services\LegacyImporter;

use App\Models\Airport;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

class AirportImporter extends BaseImporter
{
    protected $table = 'airports';

    public function run($start = 0): bool
    {
        $this->comment('--- AIRPORT IMPORT ---');

        // Legacy name to current name
        $set_if_exists = [
            'iata'                 => 'iata',
            'city'                 => 'location',
            'ground_handling_cost' => 'ground_handling_cost',
            'fuelprice'            => 'fuel_jeta_cost',
            'tz'                   => 'timezone',
            'elevation'            => 'elevation',
            'region'               => 'region',
        ];

        $count = 0;
        $rows = $this->db->readRows($this->table, $this->idField, $start);
        foreach ($rows as $row) {
            $attrs = [
                'id'      => trim((string) $row->icao),
                'icao'    => trim((string) $row->icao),
                'name'    => $row->name,
                'country' => $row->country,
                'lat'     => $row->lat,
                'lon'     => $row->lng,
                'hub'     => $row->hub,
            ];

            foreach ($set_if_exists as $legacy_name => $current_name) {
                if (property_exists($row, $legacy_name) && !empty($row->{$legacy_name})) {
                    $attrs[$current_name] = $row->{$legacy_name};
                }
            }

            $w = ['id' => $attrs['id']];
            // $airport = Airport::updateOrCreate($w, $attrs);

            try {
                $airport = Airport::create(array_merge($w, $attrs));
            } catch (QueryException $e) {
                $sqlState = $e->errorInfo[0];
                $errorCode = $e->errorInfo[1];
                if ($sqlState === '23000' && $errorCode === 1062) {
                    Log::info('Found duplicate for '.$row->icao.', ignoring');

                    return true;
                }

                return false;
            }

            if ($airport->wasRecentlyCreated) {
                $count++;
            }
        }

        $this->info('Imported '.$count.' airports');

        return true;
    }
}

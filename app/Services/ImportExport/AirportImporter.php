<?php

namespace App\Services\ImportExport;

use App\Contracts\ImportExport;
use App\Models\Airport;

/**
 * Import airports.
 */
class AirportImporter extends ImportExport
{
    public $assetType = 'airport';

    /**
     * All of the columns that are in the CSV import
     * Should match the database fields, for the most part.
     */
    public static $columns = [
        'icao'                 => 'required',
        'iata'                 => 'nullable',
        'name'                 => 'required',
        'location'             => 'nullable',
        'region'               => 'nullable',
        'country'              => 'nullable',
        'timezone'             => 'nullable',
        'hub'                  => 'nullable|boolean',
        'lat'                  => 'required|numeric',
        'lon'                  => 'required|numeric',
        'elevation'            => 'nullable|numeric',
        'ground_handling_cost' => 'nullable|numeric',
        'fuel_100ll_cost'      => 'nullable|numeric',
        'fuel_jeta_cost'       => 'nullable|numeric',
        'fuel_mogas_cost'      => 'nullable|numeric',
        'notes'                => 'nullable',
    ];

    /**
     * Import a flight, parse out the different rows.
     *
     * @param int $index
     */
    public function import(array $row, $index): bool
    {
        $row['id'] = $row['icao'];
        $row['hub'] = get_truth_state($row['hub']);

        if (!is_numeric($row['ground_handling_cost'])) {
            $row['ground_handling_cost'] = (float) setting('airports.default_ground_handling_cost');
        } else {
            $row['ground_handling_cost'] = (float) $row['ground_handling_cost'];
        }

        if (!is_numeric($row['fuel_jeta_cost'])) {
            $row['fuel_jeta_cost'] = (float) setting('airports.default_jet_a_fuel_cost');
        } else {
            $row['fuel_jeta_cost'] = (float) $row['fuel_jeta_cost'];
        }

        try {
            Airport::updateOrCreate([
                'id' => $row['icao'],
            ], $row);
        } catch (\Exception $e) {
            $this->errorLog('Error in row '.($index + 1).': '.$e->getMessage());

            return false;
        }

        $this->log('Imported '.$row['icao']);

        return true;
    }
}

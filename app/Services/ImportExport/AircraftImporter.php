<?php

namespace App\Services\ImportExport;

use App\Contracts\ImportExport;
use App\Models\Aircraft;
use App\Models\Airline;
use App\Models\Enums\AircraftState;
use App\Models\Enums\AircraftStatus;
use App\Models\Subfleet;
use App\Support\ICAO;

/**
 * Import aircraft
 */
class AircraftImporter extends ImportExport
{
    public $assetType = 'aircraft';

    /**
     * All of the columns that are in the CSV import
     * Should match the database fields, for the most part
     */
    public static $columns = [
        'subfleet'     => 'required',
        'iata'         => 'nullable',
        'icao'         => 'nullable',
        'hub_id'       => 'nullable',
        'airport_id'   => 'nullable',
        'name'         => 'required',
        'registration' => 'required',
        'fin'          => 'nullable',
        'hex_code'     => 'nullable',
        'mtow'         => 'nullable|numeric',
        'zfw'          => 'nullable|numeric',
        'status'       => 'nullable',
    ];

    /**
     * Find the subfleet specified, or just create it on the fly and attach it to the
     * first airline that's been found
     *
     * @param $type
     *
     * @return Subfleet|\Illuminate\Database\Eloquent\Model|null|object|static
     */
    protected function getSubfleet($type)
    {
        return Subfleet::firstOrCreate([
            'type' => $type,
        ], [
            'name'       => $type,
            'airline_id' => Airline::where('active', true)->first()->id,
        ]);
    }

    /**
     * Import an aircraft, parse out the different rows
     *
     * @param array $row
     * @param int   $index
     *
     * @throws \Exception
     *
     * @return bool
     */
    public function import(array $row, $index): bool
    {
        $subfleet = $this->getSubfleet($row['subfleet']);
        $row['subfleet_id'] = $subfleet->id;

        // Generate a hex code
        if (!$row['hex_code']) {
            $row['hex_code'] = ICAO::createHexCode();
        }

        // Set a default status
        $row['status'] = trim($row['status']);
        if (empty($row['status'])) {
            $row['status'] = AircraftStatus::ACTIVE;
        }

        // Just set its state right now as parked
        $row['state'] = AircraftState::PARKED;

        // Check FIN and set to null if it is blank
        $row['fin'] = blank($row['fin']) ? null : $row['fin'];

        // Try to add or update
        try {
            Aircraft::updateOrCreate([
                'registration' => $row['registration'],
            ], $row);
        } catch (\Exception $e) {
            $this->errorLog('Error in row '.($index + 1).': '.$e->getMessage());
            return false;
        }

        $this->log('Imported '.$row['registration'].' '.$row['name']);
        return true;
    }
}

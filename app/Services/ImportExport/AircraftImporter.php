<?php

namespace App\Services\ImportExport;

use App\Contracts\ImportExport;
use App\Models\Aircraft;
use App\Models\Airline;
use App\Models\Enums\AircraftState;
use App\Models\Enums\AircraftStatus;
use App\Models\Subfleet;
use App\Support\ICAO;
use App\Support\Units\Mass;
use Exception;
use Illuminate\Database\Eloquent\Model;

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
        'subfleet'      => 'required',
        'iata'          => 'nullable',
        'icao'          => 'nullable',
        'hub_id'        => 'nullable',
        'airport_id'    => 'nullable',
        'name'          => 'required',
        'registration'  => 'required',
        'fin'           => 'nullable',
        'hex_code'      => 'nullable',
        'selcal'        => 'nullable',
        'dow'           => 'nullable|numeric',
        'zfw'           => 'nullable|numeric',
        'mtow'          => 'nullable|numeric',
        'mlw'           => 'nullable|numeric',
        'status'        => 'nullable',
        'simbrief_type' => 'nullable',
    ];

    /**
     * Find the subfleet specified, or just create it on the fly and attach it to the
     * first airline that's been found
     *
     *
     * @return Subfleet|Model|null|object|static
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
     * @param int $index
     *
     * @throws Exception
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

        // Check fields and set to null if they are blank
        // Somehow they got empty strings instead of null without this!
        $row['fin'] = blank($row['fin']) ? null : $row['fin'];
        $row['selcal'] = blank($row['selcal']) ? null : $row['selcal'];
        $row['simbrief_type'] = blank($row['simbrief_type']) ? null : $row['simbrief_type'];
        // Set the correct mass units
        $row['dow'] = $this->CorrectMassUnit($row['dow']);
        $row['zfw'] = $this->CorrectMassUnit($row['zfw']);
        $row['mtow'] = $this->CorrectMassUnit($row['mtow']);
        $row['mlw'] = $this->CorrectMassUnit($row['mlw']);

        // Try to add or update
        try {
            Aircraft::updateOrCreate([
                'registration' => $row['registration'],
            ], $row);
        } catch (Exception $e) {
            $this->errorLog('Error in row '.($index + 1).': '.$e->getMessage());

            return false;
        }

        $this->log('Imported '.$row['registration'].' '.$row['name']);

        return true;
    }

    public function CorrectMassUnit($value)
    {
        if ($value > 0) {
            return Mass::make((float) $value, setting('units.weight'));
        }

        return null;
    }
}

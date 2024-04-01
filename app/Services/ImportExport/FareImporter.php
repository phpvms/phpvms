<?php

namespace App\Services\ImportExport;

use App\Contracts\ImportExport;
use App\Models\Fare;

/**
 * Import aircraft
 */
class FareImporter extends ImportExport
{
    public $assetType = 'fare';

    /**
     * All of the columns that are in the CSV import
     * Should match the database fields, for the most part
     */
    public static $columns = [
        'code'     => 'required',
        'name'     => 'required',
        'type'     => 'required',
        'price'    => 'nullable|numeric',
        'cost'     => 'nullable|numeric',
        'capacity' => 'required|integer',
        'notes'    => 'nullable',
        'active'   => 'nullable|boolean',
    ];

    /**
     * Import a flight, parse out the different rows
     *
     * @param array $row
     * @param int   $index
     *
     * @return bool
     */
    public function import(array $row, $index): bool
    {
        try {
            // Try to add or update
            $fare = Fare::updateOrCreate([
                'code' => $row['code'],
            ], $row);
        } catch (\Exception $e) {
            $this->errorLog('Error in row '.($index + 1).': '.$e->getMessage());
            return false;
        }

        $this->log('Imported '.$row['code'].' '.$row['name']);
        return true;
    }
}

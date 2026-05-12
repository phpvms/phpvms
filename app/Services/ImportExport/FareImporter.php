<?php

declare(strict_types=1);

namespace App\Services\ImportExport;

use App\Contracts\ImportExport;
use App\Models\Fare;
use Exception;

/**
 * Import aircraft
 */
class FareImporter extends ImportExport
{
    public string $assetType = 'fare';

    /**
     * All of the columns that are in the CSV import
     * Should match the database fields, for the most part
     */
    public static array $columns = [
        'code'     => 'required',
        'name'     => 'required',
        'type'     => 'required',
        'price'    => 'nullable|numeric',
        'cost'     => 'nullable|numeric',
        'capacity' => 'nullable|integer',
        'notes'    => 'nullable',
        'active'   => 'nullable|boolean',
    ];

    /**
     * Import a flight, parse out the different rows
     */
    public function import(array $row, int $index): bool
    {
        try {
            // Try to add or update
            $fare = Fare::updateOrCreate([
                'code' => $row['code'],
            ], $row);
        } catch (Exception $exception) {
            $this->errorLog('Error in row '.($index + 1).': '.$exception->getMessage());

            return false;
        }

        $this->log('Imported '.$row['code'].' '.$row['name']);

        return true;
    }
}

<?php

namespace App\Services\ImportExport;

use App\Contracts\ImportExport;
use App\Models\Airport;

/**
 * The flight importer can be imported or export. Operates on rows.
 */
class AirportExporter extends ImportExport
{
    public $assetType = 'airport';

    /**
     * Set the current columns and other setup.
     */
    public function __construct()
    {
        self::$columns = array_keys(AirportImporter::$columns);
    }

    /**
     * Import a flight, parse out the different rows.
     *
     * @param Airport $airport
     */
    public function export($airport): array
    {
        $ret = [];
        foreach (self::$columns as $column) {
            $ret[$column] = $airport->{$column};
        }

        return $ret;
    }
}

<?php

namespace App\Services\ImportExport;

use App\Contracts\ImportExport;
use App\Models\Aircraft;

/**
 * The flight importer can be imported or export. Operates on rows
 */
class AircraftExporter extends ImportExport
{
    public $assetType = 'aircraft';

    /**
     * Set the current columns and other setup
     */
    public function __construct()
    {
        self::$columns = array_keys(AircraftImporter::$columns);
    }

    /**
     * Import a flight, parse out the different rows
     *
     * @param Aircraft $aircraft
     */
    public function export($aircraft): array
    {
        $ret = [];
        foreach (self::$columns as $column) {
            if ($column === 'subfleet') {
                $ret['subfleet'] = $aircraft->subfleet->type;
            } else {
                $ret[$column] = $aircraft->{$column};
            }
        }

        return $ret;
    }
}

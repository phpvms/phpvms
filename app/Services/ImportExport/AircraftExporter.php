<?php

declare(strict_types=1);

namespace App\Services\ImportExport;

use App\Contracts\ImportExport;
use App\Models\Aircraft;
use Illuminate\Database\Eloquent\Model;

/**
 * The flight importer can be imported or export. Operates on rows
 */
class AircraftExporter extends ImportExport
{
    public string $assetType = 'aircraft';

    /**
     * Set the current columns and other setup
     */
    public function __construct()
    {
        self::$columns = array_keys(AircraftImporter::$columns);
    }

    /**
     * Import a flight, parse out the different rows
     */
    public function export(Model $row): array
    {
        if (!$row instanceof Aircraft) {
            throw new \InvalidArgumentException('Expected Aircraft Model');
        }

        $ret = [];
        foreach (self::$columns as $column) {
            if ($column === 'subfleet') {
                $ret['subfleet'] = $row->subfleet->type;
            } elseif ($column === 'state') {
                $ret[$column] = $row->state->value;
            } elseif ($column === 'status') {
                $ret[$column] = $row->status->value;
            } else {
                $ret[$column] = $row->{$column};
            }
        }

        return $ret;
    }
}

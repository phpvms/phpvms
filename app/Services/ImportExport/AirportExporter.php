<?php

declare(strict_types=1);

namespace App\Services\ImportExport;

use App\Contracts\ImportExport;
use App\Models\Airport;
use Illuminate\Database\Eloquent\Model;

/**
 * The flight importer can be imported or export. Operates on rows
 */
class AirportExporter extends ImportExport
{
    public string $assetType = 'airport';

    /**
     * Set the current columns and other setup
     */
    public function __construct()
    {
        self::$columns = array_keys(AirportImporter::$columns);
    }

    /**
     * Import a flight, parse out the different rows
     */
    public function export(Model $row): array
    {
        if (!$row instanceof Airport) {
            throw new \InvalidArgumentException('Expected Airport Model');
        }

        $ret = [];
        foreach (self::$columns as $column) {
            $ret[$column] = $row->{$column};
        }

        return $ret;
    }
}

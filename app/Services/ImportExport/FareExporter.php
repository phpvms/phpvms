<?php

declare(strict_types=1);

namespace App\Services\ImportExport;

use App\Contracts\ImportExport;
use App\Models\Fare;
use Illuminate\Database\Eloquent\Model;

/**
 * The flight importer can be imported or export. Operates on rows
 */
class FareExporter extends ImportExport
{
    public string $assetType = 'fare';

    /**
     * Set the current columns and other setup
     */
    public function __construct()
    {
        self::$columns = array_keys(FareImporter::$columns);
    }

    /**
     * Import a flight, parse out the different rows
     */
    public function export(Model $row): array
    {
        if (!$row instanceof Fare) {
            throw new \InvalidArgumentException('Expected Fare Model');
        }

        $ret = [];
        foreach (self::$columns as $column) {
            $ret[$column] = $row->{$column};
        }

        return $ret;
    }
}

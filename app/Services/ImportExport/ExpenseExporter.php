<?php

declare(strict_types=1);

namespace App\Services\ImportExport;

use App\Contracts\ImportExport;
use App\Models\Aircraft;
use App\Models\Airport;
use App\Models\Expense;
use App\Models\Subfleet;
use Illuminate\Database\Eloquent\Model;

/**
 * Import expenses
 */
class ExpenseExporter extends ImportExport
{
    public string $assetType = 'expense';

    /**
     * Set the current columns and other setup
     */
    public function __construct()
    {
        self::$columns = array_keys(ExpenseImporter::$columns);
    }

    /**
     * Import a flight, parse out the different rows
     */
    public function export(Model $row): array
    {
        if (!$row instanceof Expense) {
            throw new \InvalidArgumentException('Expected Expense Model');
        }

        $ret = [];

        foreach (self::$columns as $col) {
            if ($col === 'airline') {
                $ret['airline'] = $row->airline?->icao;
            } elseif ($col === 'flight_type') {
                $ret['flight_type'] = $row->flight_type;
            } elseif ($col === 'type') {
                $ret['type'] = $row->type->value;
            } else {
                $ret[$col] = $row->{$col};
            }
        }

        // For the different expense types, instead of exporting
        // the ID, export a specific column
        if ($row->ref_model instanceof Expense) {
            $ret['ref_model_type'] = '';
            $ret['ref_model_id'] = '';
        } else {
            if (!$row->ref_model) { // bail out
                return $ret;
            }

            if ($row->ref_model instanceof Aircraft) {
                $ret['ref_model_id'] = $row->ref_model->registration;
            } elseif ($row->ref_model instanceof Airport) {
                $ret['ref_model_id'] = $row->ref_model->icao;
            } elseif ($row->ref_model instanceof Subfleet) {
                $ret['ref_model_id'] = $row->ref_model->type;
            }
        }

        // And convert the ref_model into the shorter name
        $ret['ref_model_type'] = str_replace('App\Models\\', '', $ret['ref_model_type']);

        return array_values($ret);
    }
}

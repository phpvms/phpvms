<?php

namespace App\Services\ImportExport;

use App\Contracts\ImportExport;
use App\Models\Aircraft;
use App\Models\Airport;
use App\Models\Expense;
use App\Models\Subfleet;

/**
 * Import expenses.
 */
class ExpenseExporter extends ImportExport
{
    public $assetType = 'expense';

    /**
     * Set the current columns and other setup.
     */
    public function __construct()
    {
        self::$columns = array_keys(ExpenseImporter::$columns);
    }

    /**
     * Import a flight, parse out the different rows.
     *
     * @param Expense $expense
     */
    public function export($expense): array
    {
        $ret = [];

        foreach (self::$columns as $col) {
            if ($col === 'airline') {
                $ret['airline'] = optional($expense->airline)->icao;
            } elseif ($col === 'flight_type') {
                $ret['flight_type'] = implode(',', $expense->flight_type);
            } else {
                $ret[$col] = $expense->{$col};
            }
        }

        // For the different expense types, instead of exporting
        // the ID, export a specific column
        if ($expense->ref_model === Expense::class) {
            $ret['ref_model'] = '';
            $ret['ref_model_id'] = '';
        } else {
            $obj = $expense->getReferencedObject();
            if (!$obj) { // bail out
                return $ret;
            }

            if ($expense->ref_model === Aircraft::class) {
                $ret['ref_model_id'] = $obj->registration;
            } elseif ($expense->ref_model === Airport::class) {
                $ret['ref_model_id'] = $obj->icao;
            } elseif ($expense->ref_model === Subfleet::class) {
                $ret['ref_model_id'] = $obj->type;
            }
        }

        // And convert the ref_model into the shorter name
        $ret['ref_model'] = str_replace('App\Models\\', '', $ret['ref_model']);

        return array_values($ret);
    }
}

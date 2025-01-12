<?php

use App\Contracts\Migration;
use App\Models\Expense;

/**
 * Update the expenses to add the airline ID.
 */
return new class() extends Migration
{
    public function up(): void
    {
        /** @var Expense[] $all_expenses */
        $all_expenses = Expense::all();
        foreach ($all_expenses as $expense) {
            $this->getAirlineId($expense);
        }
    }

    /**
     * Figure out the airline ID.
     */
    public function getAirlineId(Expense $expense): void
    {
        $klass = 'Expense';
        if ($expense->ref_model) {
            $ref = explode('\\', $expense->ref_model);
            $klass = end($ref);
            $obj = $expense->getReferencedObject();
        }

        if (empty($obj)) {
            return;
        }

        if ($klass === 'Airport') {
            // TODO: Get an airline ID?
        } elseif ($klass === 'Subfleet') {
            $expense->airline_id = $obj->airline_id;
        } elseif ($klass === 'Aircraft') {
            $expense->airline_id = $obj->airline->id;
        }

        $expense->save();
    }
};

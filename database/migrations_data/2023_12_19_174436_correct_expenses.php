<?php

use App\Contracts\Migration;
use App\Models\Aircraft;
use App\Models\Airport;
use App\Models\Expense;
use App\Models\Subfleet;

/**
 * Update the expenses to add the airline ID
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
     * Figure out the airline ID
     */
    public function getAirlineId(Expense $expense): void
    {
        if (!$expense->ref_model) {
            return;
        }

        if ($expense->ref_model instanceof Airport) {
            // TODO: Get an airline ID?
        } elseif ($expense->ref_model instanceof Subfleet) {
            $expense->airline_id = $expense->ref_model->airline_id;
        } elseif ($expense->ref_model instanceof Aircraft) {
            $expense->airline_id = $expense->ref_model->airline->id;
        }

        $expense->save();
    }
};

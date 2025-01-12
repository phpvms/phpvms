<?php

namespace App\Models\Traits;

use App\Models\Expense;

trait ExpensableTrait
{
    public static function bootExpensableTrait()
    {
    }

    /**
     * Morph to Expenses.
     *
     * @return mixed
     */
    public function expenses()
    {
        return $this->morphMany(
            Expense::class,
            'expenses',  // overridden by the next two anyway
            'ref_model',
            'ref_model_id'
        );
    }
}

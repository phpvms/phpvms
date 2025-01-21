<?php

namespace App\Listeners;

use App\Contracts\Listener;
use App\Events\Expenses;
use App\Models\Enums\ExpenseType;
use App\Models\Expense;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class ExpenseListener extends Listener // implements ShouldQueue
{
    // use Queueable;

    /**
     * Return a list of additional expenses.
     *
     *
     * @return mixed
     */
    public function handle(Expenses $event)
    {
        $expenses = [];

        // This is an example of an expense to return
        // You have the pirep on $event->pirep, and any associated data
        // The transaction group is how it will show as a line item
        /*$expenses[] = new Expense([
            'type' => ExpenseType::FLIGHT,
            'amount' => 150,  # $150
            'transaction_group' => '',
            'charge_to_user' => true|false
        ]);*/

        return $expenses;
    }
}

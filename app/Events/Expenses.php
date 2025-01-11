<?php

namespace App\Events;

use App\Contracts\Event;
use App\Models\Pirep;

/**
 * This event is dispatched when the expenses for a flight report
 * are collected. Your listeners should return a list of Expense
 * models. Don't call save on the model!
 *
 * Example return:
 *
 * [
 *   new Expense([
 *      'airline_id':   '',    # < optional field
 *      'name':         '',
 *      'amount':       [DECIMAL],
 *      'type':         int from ExpenseType enum class
 *   ]),
 * ]
 *
 * The event caller will check the 'type' to make sure that it
 * will filter out expenses that only apply to the current process
 *
 * The event will have a copy of the PIREP model, if it's applicable
 */
class Expenses extends Event
{
    public ?Pirep $pirep;

    public function __construct(?Pirep $pirep = null)
    {
        $this->pirep = $pirep;
    }
}

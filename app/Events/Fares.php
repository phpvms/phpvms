<?php

namespace App\Events;

use App\Contracts\Event;
use App\Models\Pirep;

/**
 * This event is dispatched when the fares for a flight report
 * are collected. Your listeners should return a list of Fare
 * models. Don't call save on the model!
 *
 * Example return:
 *
 *   new Fare([
 *      'name'  => '', # displayed as the memo
 *      'type'  => [INTEGER], # from FareType enum class
 *      'price' => [DECIMAL],
 *      'cost'  => [DECIMAL], # optional
 *      'notes' => '', # used as Transaction Group
 *   ]);
 *
 * The event caller will check the 'type' to make sure that it
 * will filter out fares that only apply to the current process
 *
 * The event will have a copy of the PIREP model, if it's applicable
 */
class Fares extends Event
{
    public function __construct(public ?Pirep $pirep = null) {}
}

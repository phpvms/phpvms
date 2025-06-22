<?php

namespace App\Events;

use App\Contracts\Event;
use App\Models\User;

class UserStatsChanged extends Event
{
    /*
     * When a user's stats change. Stats changed match the field name:
     *   airport
     *   flights
     *   rank
     */
    public function __construct(public User $user, public $stat_name, public $old_value) {}
}

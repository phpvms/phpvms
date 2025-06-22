<?php

namespace App\Events;

use App\Contracts\Event;
use App\Models\User;

/**
 * See if this user has won any awards
 */
class ProcessAward extends Event
{
    public function __construct(public User $user) {}
}

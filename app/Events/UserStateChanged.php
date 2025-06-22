<?php

namespace App\Events;

use App\Contracts\Event;
use App\Models\User;

/**
 * Event triggered when a user's state changes
 */
class UserStateChanged extends Event
{
    public function __construct(public User $user, public $old_state) {}
}

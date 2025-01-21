<?php

namespace App\Events;

use App\Contracts\Event;
use App\Models\User;

/**
 * Event triggered when a user's state changes.
 */
class UserStateChanged extends Event
{
    public User $user;

    public $old_state;

    public function __construct(User $user, $old_state)
    {
        $this->user = $user;
        $this->old_state = $old_state;
    }
}

<?php

namespace App\Events;

use App\Contracts\Event;
use App\Models\User;

class TestEvent extends Event
{
    public function __construct(public User $user) {}
}

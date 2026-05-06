<?php

declare(strict_types=1);

namespace App\Events;

use App\Contracts\Event;
use App\Models\User;

class TestEvent extends Event
{
    public function __construct(public User $user) {}
}

<?php

namespace App\Events;

use App\Contracts\Event;
use App\Models\UserAward;

class AwardAwarded extends Event
{
    public function __construct(public UserAward $userAward) {}
}

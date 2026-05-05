<?php

declare(strict_types=1);

namespace App\Contracts;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class Event
{
    use Dispatchable;
    use SerializesModels;
}

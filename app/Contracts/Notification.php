<?php

declare(strict_types=1);

namespace App\Contracts;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class Notification extends \Illuminate\Notifications\Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Whether NotificationsSubscriber::notifyAllUsers() should limit this
     * notification to users who opted in.
     */
    public $requires_opt_in = false;
}

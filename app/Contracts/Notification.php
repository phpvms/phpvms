<?php

namespace App\Contracts;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class Notification extends \Illuminate\Notifications\Notification implements ShouldQueue
{
    use Queueable;

    public $channels = [];

    public $requires_opt_in = false;

    public function __construct()
    {
        // Look in the notifications.channels config and see where this particular
        // notification can go. Map it to $channels
        /*$klass = static::class;
        $notif_config = config('notifications.channels', []);
        if (!array_key_exists($klass, $notif_config)) {
            Log::error('Notification type '.$klass.' missing from notifications config, defaulting to mail');
            return;
        }

        $this->channels = $notif_config[$klass];*/
    }
}

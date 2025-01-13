<?php

namespace App\Contracts;

use Illuminate\Contracts\Events\Dispatcher;

abstract class Listener
{
    public static $callbacks = [];

    /**
     * Sets up any callbacks that are defined in the child class.
     */
    public function subscribe(Dispatcher $events): void
    {
        foreach (static::$callbacks as $klass => $cb) {
            $events->listen($klass, static::class.'@'.$cb);
        }
    }
}

<?php

namespace App\Listeners;

use Illuminate\Log\Events\MessageLogged;
use Symfony\Component\Console\Output\ConsoleOutput;

/**
 * Show logs in the console
 *
 * https://stackoverflow.com/questions/48264479/log-laravel-with-artisan-output
 */
class MessageLoggedListener
{
    public function handle(MessageLogged $event)
    {
        if (app()->runningInConsole() && app()->environment() !== 'testing') {
            $output = new ConsoleOutput();
            $output->writeln("<$event->level>$event->message</$event->level>");
        }
    }
}

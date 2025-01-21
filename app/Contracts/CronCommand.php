<?php

namespace App\Contracts;

abstract class CronCommand extends Command
{
    /**
     * @return mixed
     */
    abstract public function callEvent();

    /**
     * Adjust the logging depending on where we're running from
     */
    public function __construct()
    {
        parent::__construct();
        $this->redirectLoggingToFile('cron');
    }
}

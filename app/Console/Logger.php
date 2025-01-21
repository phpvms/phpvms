<?php

namespace App\Console;

use Monolog\Handler\StreamHandler;

/**
 * Just a simple custom logger that dumps to the console
 */
class Logger
{
    public function __invoke(array $config)
    {
        $logger = new \Monolog\Logger('console');

        try {
            $logger->pushHandler(new StreamHandler('php://stdout'));
        } catch (\Exception $e) {
        }

        return $logger;
    }
}

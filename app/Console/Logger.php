<?php

declare(strict_types=1);

namespace App\Console;

use Exception;
use Monolog\Handler\StreamHandler;

/**
 * Just a simple custom logger that dumps to the console
 */
class Logger
{
    public function __invoke(array $config): \Monolog\Logger
    {
        $logger = new \Monolog\Logger('console');

        try {
            $logger->pushHandler(new StreamHandler('php://stdout'));
        } catch (Exception) {
        }

        return $logger;
    }
}

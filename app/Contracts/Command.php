<?php

namespace App\Contracts;

use Generator;

/**
 * Class BaseCommand
 */
abstract class Command extends \Illuminate\Console\Command
{
    /**
     * @return mixed
     */
    abstract public function handle();

    /**
     * Adjust the logging depending on where we're running from
     */
    public function __construct()
    {
        parent::__construct();

        // Running in the console but not in the tests
        /*if (app()->runningInConsole() && env('APP_ENV') !== 'testing') {
            $this->redirectLoggingToFile('stdout');
        }*/
    }

    /**
     * Return the signature of the command
     */
    public function getSignature(): string
    {
        return $this->signature;
    }

    /**
     * Streaming file reader
     */
    public function readFile($filename): ?Generator
    {
        $fp = fopen($filename, 'rb');
        while (($line = fgets($fp)) !== false) {
            $line = rtrim($line, "\r\n");
            if ($line[0] === ';') {
                continue;
            }

            yield $line;
        }

        fclose($fp);
    }
}

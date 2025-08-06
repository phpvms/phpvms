<?php

namespace App\Contracts;

use Exception;
use Generator;
use Illuminate\Log\Logger;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Exception\LogicException;
use Symfony\Component\Process\Exception\RuntimeException;
use Symfony\Component\Process\Process;

use function is_array;

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
     * Splice the logger and replace the active handlers with the handlers from the
     * a stack in config/logging.php
     *
     * @param string $channel_name Channel name from config/logging.php
     */
    public function redirectLoggingToFile($channel_name): void
    {
        $logger = app(Logger::class);

        // Close the existing loggers
        try {
            $handlers = $logger->getHandlers();
            foreach ($handlers as $handler) {
                $handler->close();
            }
        } catch (Exception $e) {
            $this->error('Error closing handlers: '.$e->getMessage());
        }

        // Open the handlers for the channel name,
        // and then set them to the main logger
        try {
            $logger->setHandlers(
                Log::channel($channel_name)->getHandlers()
            );
        } catch (Exception $e) {
            $this->error('Couldn\'t splice the logger: '.$e->getMessage());
        }
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

    /**
     * @param array|string $cmd
     * @param bool         $return
     * @param mixed        $verbose
     *
     * @throws RuntimeException
     * @throws LogicException
     */
    public function runCommand($cmd, $return = false, $verbose = true): string
    {
        if (is_array($cmd)) {
            $cmd = implode(' ', $cmd);
        }

        if ($verbose) {
            $this->info('Running '.$cmd);
        }

        $val = '';
        $process = Process::fromShellCommandline($cmd);
        $process->run(function ($type, $buffer) use ($return, &$val) {
            if ($return) {
                $val .= $buffer;
            } else {
                echo $buffer;
            }
        });

        return $val;
    }
}

<?php

namespace App\Services\Installer;

use App\Contracts\Service;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

class StreamedCommandsService extends Service
{
    /**
     * @throws \RuntimeException
     */
    public function streamArtisanCommand(array $command, \Closure $streamCallback): void
    {
        if (!function_exists('proc_open')) {
            throw new \RuntimeException('You can\'t use artisan streamed commands without proc_open');
        }

        $finder = new PhpExecutableFinder();
        $php_path = $finder->find(false);
        $php = str_replace('-fpm', '', $php_path);

        // If this is the cgi version of the exec, add this arg, otherwise there's
        // an error with no arguments existing
        if (str_contains($php, '-cgi')) {
            $php .= ' -d register_argc_argv=On';
        }

        $artisan = base_path('artisan');
        $command = [$php, $artisan, ...$command];

        $process = new Process($command);
        $process->setTimeout(120);
        $process->run(function ($type, $buffer) use ($streamCallback) {
            $streamCallback($buffer);
        });
    }
}

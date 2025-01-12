<?php

namespace App\Services;

use App\Contracts\Service;
use App\Repositories\KvpRepository;
use DateTime;
use DateTimeZone;
use Exception;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\PhpExecutableFinder;

class CronService extends Service
{
    public function __construct(
        private readonly KvpRepository $kvpRepo
    ) {
    }

    /**
     * Get the path for running a cron job.
     */
    public function getCronPath(): string
    {
        $finder = new PhpExecutableFinder();
        $php_path = $finder->find(false);
        $php_exec = str_replace('-fpm', '', $php_path);

        // If this is the cgi version of the exec, add this arg, otherwise there's
        // an error with no arguments existing
        if (str_contains($php_exec, '-cgi')) {
            $php_exec .= ' -d register_argc_argv=On';
        }

        $command = base_path('bin/cron');

        // If the server has proc_open then use the default laravel scheduler
        if (function_exists('proc_open')) {
            $command = base_path('artisan schedule:run');
        }

        $path = [
            $php_exec,
            $command,
        ];

        return implode(' ', $path);
    }

    /**
     * Show an example cron command that runs every minute.
     */
    public function getCronExecString(): string
    {
        return implode(' ', [
            '* * * * *',
            $this->getCronPath(),
            '>> /dev/null 2>&1',
        ]);
    }

    /**
     * Update the last time the cron was run in the kvp repo.
     */
    public function updateLastRunTime()
    {
        $dt = new DateTime('now', new DateTimeZone('UTC'));
        $this->kvpRepo->save('cron_last_run', $dt->format(DateTime::ISO8601));
    }

    /**
     * True/false if there's a problem with the cron. Now this is mainly
     * if the cron hasn't run in the last 5 minutes at least.
     */
    public function cronProblemExists(): bool
    {
        $last_run = $this->kvpRepo->get('cron_last_run');
        if (empty($last_run)) {
            return true;
        }

        try {
            $dt = DateTime::createFromFormat(DateTime::ISO8601, $last_run);
            $dt_now = new DateTime('now', new DateTimeZone('UTC'));
        } catch (Exception $e) {
            Log::error('Error checking for cron problem: '.$e->getMessage());

            return true;
        }

        // More than 5 minutes... there's a problem
        $diff = $dt_now->diff($dt);

        return $diff->i > 60 * 12;  // Hasn't run for 12 hours
    }
}

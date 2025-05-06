<?php

namespace App\Console\Commands;

use App;
use App\Contracts\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class ProcessQueue extends Command
{
    protected $signature = 'queue:cron';

    protected $description = 'Process the queue from a cron job';

    /**
     * Run the queue tasks
     */
    public function handle()
    {
        Artisan::call('queue:work', [
            // '--sansdaemon'      => null,
            '--stop-when-empty' => null,
        ]);

        $jobOutput = trim(Artisan::output());
        if ($jobOutput !== '' && $jobOutput !== '0') {
            Log::info($jobOutput);
        }

        // /** @var App\Support\WorkCommand $queueWorker */
        // $queueWorker = new App\Support\WorkCommand(app('queue.worker'), app('cache.store'));

        // $queueWorker->setInput($queueWorker->createInputFromArguments([]));
        // $queueWorker->handle();

        /*$output = $this->call('queue:work', [
            '--stop-when-empty' => null,
        ]);

        Log::info($output);*/
    }
}

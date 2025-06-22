<?php

namespace App\Http\Controllers\Api;

use App\Console\Cron;
use App\Console\Kernel;
use App\Contracts\Controller;
use App\Exceptions\CronInvalid;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MaintenanceController extends Controller
{
    /**
     * Run the cron job from the web
     *
     * @param string $id The ID passed in for the cron
     */
    public function cron(Request $request, string $id): JsonResponse
    {
        $cron_id = setting('cron.random_id');
        throw_if(empty($cron_id) || $id !== $cron_id, new CronInvalid());

        // Create a console kernel instance
        $consoleKernel = app()->make(Kernel::class);

        // Run a null artisan thing just so Laravel internals can be setup properly
        $consoleKernel->handle(
            new \Symfony\Component\Console\Input\ArgvInput(),
            new \Symfony\Component\Console\Output\NullOutput()
        );

        $cron = app(Cron::class);
        $run = $cron->run();

        return response()->json([
            'count' => count($run),
            'tasks' => $run,
        ]);
    }
}

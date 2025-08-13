<?php

namespace App\Http\Controllers\Api;

use App\Console\Cron;
use App\Console\Kernel;
use App\Contracts\Controller;
use App\Exceptions\CronInvalid;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\NullOutput;

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
        if (empty($cron_id) || $id !== $cron_id) {
            throw new CronInvalid();
        }

        // Create a console kernel instance
        $consoleKernel = app()->make(Kernel::class);

        // Run a null artisan thing just so Laravel internals can be setup properly
        $status = $consoleKernel->handle(
            new ArgvInput(),
            new NullOutput()
        );

        $cron = app(Cron::class);
        $run = $cron->run();

        return response()->json([
            'count' => count($run),
            'tasks' => $run,
        ]);
    }
}

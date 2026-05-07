<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Controller;
use App\Exceptions\CronInvalid;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class MaintenanceController extends Controller
{
    /**
     * Run the cron job from the web
     */
    public function cron(Request $request, string $id): JsonResponse
    {
        $cronId = setting('cron.random_id');

        if (empty($cronId) || !hash_equals($cronId, $id)) {
            throw new CronInvalid();
        }

        // Run Laravel's scheduler
        $exitCode = Artisan::call('schedule:run');

        // Capture scheduler output
        $output = trim(Artisan::output());

        return response()->json([
            'success'   => $exitCode === 0,
            'exit_code' => $exitCode,
            'timestamp' => now()->toIso8601String(),
            'output'    => $output === '' || $output === '0'
                ? ['No scheduled tasks were ready to run.']
                : preg_split('/\r\n|\r|\n/', $output),
        ]);
    }
}

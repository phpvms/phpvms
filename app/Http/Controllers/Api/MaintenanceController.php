<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Controller;
use App\Exceptions\CronInvalid;
use App\Features\Setup\Actions\RunSetup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Context;

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

        // Run Laravel's scheduler; setup-run skips itself here (RunSetup::WEB_CRON).
        Context::addHidden(RunSetup::WEB_CRON, true);

        try {
            $exitCode = Artisan::call('schedule:run');
        } finally {
            Context::forgetHidden(RunSetup::WEB_CRON);
        }

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

<?php

namespace App\Services;

use App\Contracts\Service;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PDO;
use VaCentral\Models\Stat;
use VaCentral\VaCentral;

class AnalyticsService extends Service
{
    /**
     * Send out some stats about the install, like the PHP and DB versions
     */
    public function sendInstall()
    {
        if (setting('general.telemetry') === false) {
            return;
        }

        $versionSvc = app(VersionService::class);
        $pdo = DB::connection()->getPdo();

        $props = [
            'php'     => PHP_VERSION,
            'db'      => strtolower($pdo->getAttribute(PDO::ATTR_SERVER_VERSION)),
            'version' => $versionSvc->getCurrentVersion(false),
        ];

        try {
            $stat = Stat::new('event', 'install', $props);
            $client = new VaCentral();
            $client->postStat($stat);
        } catch (Exception $e) {
            Log::error($e->getMessage());
        }
    }

    public function sendUpdate()
    {
        if (setting('general.telemetry') === false) {
            return;
        }

        $versionSvc = app(VersionService::class);
        $props = [
            'version' => $versionSvc->getCurrentVersion(false),
        ];

        try {
            $stat = Stat::new('event', 'update', $props);
            $client = new VaCentral();
            $client->postStat($stat);
        } catch (Exception $e) {
            Log::error($e->getMessage());
        }
    }
}

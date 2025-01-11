<?php

namespace App\Http\Controllers\System;

use App\Contracts\Controller;
use App\Services\Installer\InstallerService;
use App\Services\Installer\MigrationService;
use App\Services\Installer\SeederService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

use function count;

class UpdateController extends Controller
{
    public function __construct(
        private readonly InstallerService $installerSvc,
        private readonly MigrationService $migrationSvc,
        private readonly SeederService $seederSvc
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        return view('system.updater.index-start');
    }

    /**
     * Step 1. Check if there's an update available. Check if there
     * are any unrun migrations
     */
    public function step1(): View
    {
        $this->installerSvc->clearCaches();

        if ($this->installerSvc->isUpgradePending()) {
            Log::info('Upgrade is pending');
        }

        return view('system.updater.steps.step1-update-available');
    }

    /**
     * Step 2 Run all of the migrations
     */
    public function run_migrations(Request $request): View
    {
        Log::info('Update: run_migrations', $request->post());

        $migrations = $this->migrationSvc->migrationsAvailable();
        $data_migrations = $this->migrationSvc->dataMigrationsAvailable();
        if (count($migrations) === 0 && count($data_migrations) === 0) {
            $this->seederSvc->syncAllSeeds();

            return view('system.updater.steps.step3-update-complete');
        }

        $output = $this->migrationSvc->runAllMigrations();
        $this->seederSvc->syncAllSeeds();
        $output .= $this->migrationSvc->runAllDataMigrations();

        return view('system.updater.steps.step2-migrations-done', [
            'console_output' => $output,
        ]);
    }

    /**
     * Final step
     */
    public function complete(): RedirectResponse
    {
        return redirect('/admin');
    }
}

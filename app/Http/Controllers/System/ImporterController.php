<?php

namespace App\Http\Controllers\System;

use App\Contracts\Controller;
use App\Services\Installer\DatabaseService;
use App\Services\LegacyImporterService;
use App\Support\Utils;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class ImporterController extends Controller
{
    public function __construct(
        private readonly DatabaseService $dbSvc,
        private readonly LegacyImporterService $importerSvc
    ) {
        Utils::disableDebugToolbar();
    }

    /**
     * Show the main page for the importer; show form for the admin email
     * and the credentials for the other database.
     */
    public function index(): View
    {
        return view('system.importer.step1-configure');
    }

    protected function testDb(Request $request): void
    {
        $this->dbSvc->checkDbConnection(
            $request->post('db_conn'),
            $request->post('db_host'),
            $request->post('db_port'),
            $request->post('db_name'),
            $request->post('db_user'),
            $request->post('db_pass')
        );
    }

    /**
     * Check the database connection.
     */
    public function dbtest(Request $request): View
    {
        $status = 'success';  // success|warn|danger
        $message = 'Database connection looks good!';

        try {
            $this->testDb($request);
        } catch (Exception $e) {
            $status = 'danger';
            $message = 'Failed! '.$e->getMessage();
        }

        return view('system.importer.dbtest', [
            'status'  => $status,
            'message' => $message,
        ]);
    }

    /**
     * The post from the above.
     */
    public function config(Request $request): View
    {
        try {
            $this->testDb($request);

            // Save the credentials to use later
            $this->importerSvc->saveCredentialsFromRequest($request);

            // Generate the import manifest
            $manifest = $this->importerSvc->generateImportManifest();
        } catch (Exception $e) {
            Log::error($e->getMessage());

            // Send it to run, step1
            return view('system.importer.error', [
                'error' => $e->getMessage(),
            ]);
        }

        // Send it to run, step1
        return view('system.importer.step2-processing', [
            'manifest' => $manifest,
        ]);
    }

    /**
     * Run the importer. Pass in query string with a few different parameters:
     *
     * stage=STAGE NAME
     * start=record_start
     *
     *
     * @throws Exception
     */
    public function run(Request $request): JsonResponse
    {
        $importer = $request->input('importer');
        $start = $request->input('start');

        Log::info('Starting stage '.$importer.' from offset '.$start);

        $this->importerSvc->run($importer, $start);

        return response()->json([
            'message' => 'completed',
        ]);
    }

    /**
     * Complete the import.
     */
    public function complete(): RedirectResponse
    {
        return redirect('/');
    }
}

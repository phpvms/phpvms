<?php

namespace App\Http\Controllers\System;

use App\Contracts\Controller;
use App\Database\seeds\ShieldSeeder;
use App\Services\AirlineService;
use App\Services\AnalyticsService;
use App\Services\Installer\ConfigService;
use App\Services\Installer\DatabaseService;
use App\Services\Installer\MigrationService;
use App\Services\Installer\RequirementsService;
use App\Services\Installer\SeederService;
use App\Services\UserService;
use App\Support\Countries;
use App\Support\Utils;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use RuntimeException;

use function in_array;

class InstallerController extends Controller
{
    /**
     * InstallerController constructor.
     *
     * @param AirlineService      $airlineSvc
     * @param AnalyticsService    $analyticsSvc
     * @param DatabaseService     $dbSvc
     * @param ConfigService       $envSvc
     * @param MigrationService    $migrationSvc
     * @param RequirementsService $reqSvc
     * @param SeederService       $seederSvc
     * @param ShieldSeeder        $shieldSeeder
     * @param UserService         $userService
     */
    public function __construct(
        private readonly AirlineService $airlineSvc,
        private readonly AnalyticsService $analyticsSvc,
        private readonly DatabaseService $dbSvc,
        private readonly ConfigService $envSvc,
        private readonly MigrationService $migrationSvc,
        private readonly RequirementsService $reqSvc,
        private readonly SeederService $seederSvc,
        private readonly ShieldSeeder $shieldSeeder,
        private readonly UserService $userService
    ) {
        Utils::disableDebugToolbar();
    }

    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        if (config('app.key') !== 'base64:zdgcDqu9PM8uGWCtMxd74ZqdGJIrnw812oRMmwDF6KY=') {
            return view('system.installer.errors.already-installed');
        }

        return view('system.installer.install.index-start');
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
     * Check the database connection
     *
     * @param Request $request
     *
     * @return View
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

        return view('system.installer.install.dbtest', [
            'status'  => $status,
            'message' => $message,
        ]);
    }

    /**
     * Check if any of the items has been marked as failed
     *
     * @param array $arr
     *
     * @return bool
     */
    protected function allPassed(array $arr): bool
    {
        foreach ($arr as $item) {
            if ($item['passed'] === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Step 1. Check the modules and permissions
     *
     * @return View
     */
    public function step1(): View
    {
        $php_version = $this->reqSvc->checkPHPVersion();
        $extensions = $this->reqSvc->checkExtensions();
        $directories = $this->reqSvc->checkPermissions();

        // Only pass if all the items in the ext and dirs are passed
        $statuses = [
            $php_version['passed'] === true,
            $this->allPassed($extensions) === true,
            $this->allPassed($directories) === true,
        ];

        // Make sure there are no false values
        $passed = !in_array(false, $statuses, true);

        return view('system.installer.install.steps.step1-requirements', [
            'php'         => $php_version,
            'extensions'  => $extensions,
            'directories' => $directories,
            'passed'      => $passed,
        ]);
    }

    /**
     * Step 2. Database Setup
     *
     * @return View
     */
    public function step2(): View
    {
        $db_types = ['mysql' => 'mysql', 'sqlite' => 'sqlite'];
        return view('system.installer.install.steps.step2-db', [
            'db_types' => $db_types,
        ]);
    }

    /**
     * Step 2a. Create the .env
     *
     * @param Request $request
     *
     * @return RedirectResponse
     */
    public function envsetup(Request $request): RedirectResponse
    {
        $log_str = $request->post();
        $log_str['db_pass'] = '';

        Log::info('ENV setup', $log_str);

        // Before writing out the env file, test the DB credentials
        try {
            $this->testDb($request);
        } catch (Exception $e) {
            Log::error('Testing db before writing configs failed');
            Log::error($e->getMessage());

            flash()->error($e->getMessage());
            return redirect(route('installer.step2'))->withInput();
        }

        // Now write out the env file
        $attrs = [
            'SITE_NAME'     => $request->post('site_name'),
            'APP_URL'       => $request->post('app_url'),
            'DB_CONNECTION' => $request->post('db_conn'),
            'DB_HOST'       => $request->post('db_host'),
            'DB_PORT'       => $request->post('db_port'),
            'DB_DATABASE'   => $request->post('db_name'),
            'DB_USERNAME'   => $request->post('db_user'),
            'DB_PASSWORD'   => $request->post('db_pass'),
            'DB_PREFIX'     => $request->post('db_prefix'),
        ];

        /*
         * Create the config files and then redirect so that the
         * framework can pickup all those configs, etc, before we
         * setup the database and stuff
         */
        try {
            $this->envSvc->createConfigFiles($attrs);
        } catch (Exception $e) {
            Log::error('Config files failed to write');
            Log::error($e->getMessage());

            flash()->error($e->getMessage());
            return redirect(route('installer.step2'))->withInput();
        }

        // Needs to redirect so it can load the new .env
        Log::info('Redirecting to database setup');
        return redirect(route('installer.dbsetup'));
    }

    /**
     * Step 2b. Setup the database
     *
     * @return RedirectResponse|View
     */
    public function dbsetup(): RedirectResponse|View
    {
        $console_out = '';

        try {
            $console_out .= $this->dbSvc->setupDB();
            $console_out .= $this->migrationSvc->runAllMigrations();
            $this->seederSvc->syncAllSeeds();
            $this->shieldSeeder->run();
        } catch (QueryException $e) {
            Log::error('Error on db setup: '.$e->getMessage());
            //dd($e);
            $this->envSvc->removeConfigFiles();
            flash()->error($e->getMessage());
            return redirect(route('installer.step2'))->withInput();
        }

        $console_out = trim($console_out);

        return view('system.installer.install.steps.step2a-db_output', [
            'console_output' => $console_out,
        ]);
    }

    /**
     * Step 3. Setup the admin user and initial settings
     *
     * @return View
     */
    public function step3(): View
    {
        return view('system.installer.install.steps.step3-user', [
            'countries' => Countries::getSelectList(),
        ]);
    }

    /**
     * Step 3 submit
     *
     * @param Request $request
     *
     * @throws RuntimeException
     * @throws Exception
     *
     * @return RedirectResponse|View
     */
    public function usersetup(Request $request): RedirectResponse|View
    {
        $validator = Validator::make($request->all(), [
            'airline_name'    => 'required',
            'airline_icao'    => 'required|size:3|unique:airlines,icao',
            'airline_country' => 'required',
            'name'            => 'required',
            'email'           => 'required|email|unique:users,email',
            'password'        => 'required|confirmed',
        ]);

        if ($validator->fails()) {
            return redirect('install/step3')
                ->withErrors($validator)
                ->withInput();
        }

        /**
         * Create the first airline
         */
        $attrs = [
            'icao'    => $request->get('airline_icao'),
            'name'    => $request->get('airline_name'),
            'country' => $request->get('airline_country'),
        ];

        $airline = $this->airlineSvc->createAirline($attrs);

        /**
         * Create the user, and associate to the airline
         * Ensure the seed data at least has one airport
         * KAUS, for giggles, though.
         */
        $attrs = [
            'name'              => $request->get('name'),
            'email'             => $request->get('email'),
            'api_key'           => Utils::generateApiKey(),
            'airline_id'        => $airline->id,
            'password'          => Hash::make($request->get('password')),
            'opt_in'            => true,
            'email_verified_at' => now(),
        ];

        $user = $this->userService->createUser($attrs, ['super_admin']);
        Log::info('User registered: ', $user->toArray());

        // Set the initial admin e-mail address
        setting_save('general.admin_email', $user->email);

        // Save telemetry setting
        setting_save('general.telemetry', get_truth_state($request->get('telemetry')));

        // Try sending telemetry info
        $this->analyticsSvc->sendInstall();

        return view('system.installer.install.steps.step3a-completed', []);
    }

    /**
     * Final step
     *
     * @return RedirectResponse|Redirector
     */
    public function complete()
    {
        return redirect('/login');
    }
}

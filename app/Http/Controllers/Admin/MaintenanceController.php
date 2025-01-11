<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\Controller;
use App\Repositories\KvpRepository;
use App\Services\CronService;
use App\Services\Installer\SeederService;
use App\Services\VersionService;
use App\Support\Utils;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Laracasts\Flash\Flash;

class MaintenanceController extends Controller
{
    public function __construct(
        private readonly CronService $cronSvc,
        private readonly KvpRepository $kvpRepo,
        private readonly VersionService $versionSvc,
        private readonly SeederService $seederSvc,
    ) {
    }

    public function index(): View
    {
        // Get the cron URL
        $cron_id = setting('cron.random_id');
        $cron_url = empty($cron_id) ? 'Not enabled' : url(route('api.maintenance.cron', $cron_id));

        return view('admin.maintenance.index', [
            'cron_url'            => $cron_url,
            'cron_path'           => $this->cronSvc->getCronExecString(),
            'cron_problem_exists' => $this->cronSvc->cronProblemExists(),
            'new_version'         => $this->kvpRepo->get('new_version_available', false),
            'new_version_tag'     => $this->kvpRepo->get('latest_version_tag'),
        ]);
    }

    /**
     * Clear caches depending on the type passed in
     */
    public function cache(Request $request): RedirectResponse
    {
        $calls = [];
        $type = $request->get('type');
        $theme_cache_file = base_path().'/bootstrap/cache/themes.php';
        $module_cache_files = base_path().'/bootstrap/cache/*_module.php';

        // When clearing the application, clear the config, module cache and the app itself
        if ($type === 'application' || $type === 'all') {
            $calls[] = 'config:cache';
            $calls[] = 'cache:clear';
            $calls[] = 'route:cache';
            $calls[] = 'clear-compiled';

            $files = File::glob($module_cache_files);
            foreach ($files as $file) {
                $module_cache = File::delete($file) ? 'Module cache file deleted' : 'Module cache file not found!';
                Log::debug($module_cache.' | '.$file);
            }
        }

        // If we want to clear only the views and theme cache but keep everything else
        if ($type === 'views' || $type === 'all') {
            $calls[] = 'view:clear';

            $theme_cache = unlink($theme_cache_file) ? 'Theme cache file deleted' : 'Theme cache file not found!';
            Log::debug($theme_cache.' | '.$theme_cache_file);
        }

        foreach ($calls as $call) {
            Artisan::call($call);
        }

        Flash::success('Cache cleared!');

        return redirect(route('admin.maintenance.index'));
    }

    public function queue(Request $request): RedirectResponse
    {
        Artisan::call('queue:flush');

        Flash::success('Failed jobs flushed!');

        return redirect(route('admin.maintenance.index'));
    }

    /**
     * Force an update check
     */
    public function forcecheck(Request $request): RedirectResponse
    {
        $this->versionSvc->isNewVersionAvailable();

        $new_version_avail = $this->kvpRepo->get('new_version_available', false);
        $new_version_tag = $this->kvpRepo->get('latest_version_tag');

        Log::info('Force check, available='.$new_version_avail.', tag='.$new_version_tag);

        if (!$new_version_avail) {
            Flash::success('No new version available');
        } else {
            Flash::success('New version available: '.$new_version_tag);
        }

        return redirect(route('admin.maintenance.index'));
    }

    /**
     * Run the module reseeding tasks
     */
    public function reseed(Request $request): RedirectResponse
    {
        $this->seederSvc->syncAllSeeds();

        return redirect(route('admin.maintenance.index'));
    }

    /**
     * Enable the cron, or if it's enabled, change the ID that is used
     */
    public function cron_enable(Request $request): RedirectResponse
    {
        $id = Utils::generateNewId(24);
        setting_save('cron.random_id', $id);

        Flash::success('Web cron refreshed!');

        return redirect(route('admin.maintenance.index'));
    }

    /**
     * Disable the web cron
     */
    public function cron_disable(Request $request): RedirectResponse
    {
        setting_save('cron.random_id', '');

        Flash::success('Web cron disabled!');

        return redirect(route('admin.maintenance.index'));
    }
}

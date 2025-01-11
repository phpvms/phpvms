<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\Controller;
use App\Repositories\KvpRepository;
use App\Repositories\NewsRepository;
use App\Repositories\PirepRepository;
use App\Repositories\UserRepository;
use App\Services\CronService;
use App\Services\NewsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Laracasts\Flash\Flash;

class DashboardController extends Controller
{
    /**
     * DashboardController constructor.
     */
    public function __construct(
        private readonly CronService $cronSvc,
        private readonly KvpRepository $kvpRepo,
        private readonly NewsRepository $newsRepo,
        private readonly NewsService $newsSvc,
        private readonly PirepRepository $pirepRepo,
        private readonly UserRepository $userRepo
    ) {}

    /**
     * Check if a new version is available by checking the VERSION file from
     * S3 and then using the semver library to do the comparison. Just show
     * a session flash file on this page that'll get cleared right away
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function checkNewVersion()
    {
        try {
            if ($this->kvpRepo->get('new_version_available', false) === true) {
                $latest_version = $this->kvpRepo->get('latest_version_tag');
                Flash::warning('New version '.$latest_version.' is available!');
            }
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            Flash::warning('Could not contact phpVMS for version check');
        }
    }

    /**
     * Show the admin dashboard
     *
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function index(Request $request): View
    {
        $this->checkNewVersion();

        return view('admin.dashboard.index', [
            'news'                => $this->newsRepo->getLatest(),
            'pending_pireps'      => $this->pirepRepo->getPendingCount(),
            'pending_users'       => $this->userRepo->getPendingCount(),
            'cron_problem_exists' => $this->cronSvc->cronProblemExists(),
        ]);
    }

    /**
     * @throws \Prettus\Validator\Exceptions\ValidatorException
     */
    public function news(Request $request): View
    {
        if ($request->isMethod('post')) {
            $attrs = $request->post();
            $attrs['user_id'] = Auth::user()->id;

            $this->newsSvc->addNews($attrs);
        } elseif ($request->isMethod('patch')) {
            $attrs = $request->post();
            $attrs['user_id'] = Auth::user()->id;

            $this->newsSvc->updateNews($attrs);
        } elseif ($request->isMethod('delete')) {
            $id = $request->input('news_id');
            $this->newsSvc->deleteNews($id);
        }

        return view('admin.dashboard.news', [
            'news' => $this->newsRepo->getLatest(),
        ]);
    }
}

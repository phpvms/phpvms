<?php

namespace App\Http\Controllers\Frontend;

use App\Contracts\Controller;
use App\Http\Presenters\DashboardPresenter;
use Illuminate\View\View;
use Inertia\Response as InertiaResponse;

/**
 * Class DashboardController
 */
class DashboardController extends Controller
{
    /**
     * Show the application dashboard.
     *
     * Data is gathered once into DashboardPresenter; the themed() macro
     * delegates the render to the active theme (Blade or SPA) with no
     * if-branching here.  See Decision D2 in skylight-dashboard-slice/design.md.
     */
    public function index(): View|InertiaResponse
    {
        $presenter = DashboardPresenter::fromAuth();

        return response()->themed('Dashboard', 'dashboard.index', $presenter);
    }
}

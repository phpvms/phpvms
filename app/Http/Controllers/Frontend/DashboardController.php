<?php

namespace App\Http\Controllers\Frontend;

use App\Contracts\Controller;
use App\Repositories\PirepRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Class DashboardController
 */
class DashboardController extends Controller
{
    /**
     * DashboardController constructor.
     */
    public function __construct(
        private readonly PirepRepository $pirepRepo
    ) {}

    /**
     * Show the application dashboard.
     */
    public function index(): View
    {
        $last_pirep = null;
        // Support retrieval of deleted relationships
        $with_pirep = [
            'aircraft'    => fn ($query) => $query->withTrashed(),
            'arr_airport' => fn ($query) => $query->withTrashed(),
            'comments',
            'dpt_airport' => fn ($query) => $query->withTrashed(),
        ];

        /** @var \App\Models\User $user */
        $user = Auth::user();
        $user->loadMissing('journal');

        try {
            $last_pirep = $this->pirepRepo->with($with_pirep)->find($user->last_pirep_id);
        } catch (\Exception) {
        }

        // Get the current airport for the weather
        $current_airport = $user->curr_airport_id ?? $user->home_airport_id;

        return view('dashboard.index', [
            'user'            => $user,
            'current_airport' => $current_airport,
            'last_pirep'      => $last_pirep,
        ]);
    }
}

<?php

namespace App\Http\Controllers\Frontend;

use App\Contracts\Controller;
use App\Models\Pirep;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Class DashboardController
 */
class DashboardController extends Controller
{
    /**
     * Show the application dashboard.
     */
    public function index(): View
    {
        // Support retrieval of deleted relationships
        $with_pirep = [
            'aircraft'    => fn ($query) => $query->withTrashed(),
            'arr_airport' => fn ($query) => $query->withTrashed(),
            'comments',
            'dpt_airport' => fn ($query) => $query->withTrashed(),
        ];

        /** @var User $user */
        $user = Auth::user();
        $user->loadMissing('journal');

        $last_pirep = null;
        if ($user->last_pirep_id) {
            $last_pirep = Pirep::with($with_pirep)->find($user->last_pirep_id);
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

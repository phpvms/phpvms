<?php

namespace App\Http\Controllers\Frontend;

use App\Contracts\Controller;
use App\Http\Data\DashboardData;
use App\Models\Pirep;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
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
     * Dual output via themed(): the SPA gets the flat, typed DashboardData DTO;
     * Blade keeps its model-rich shape. Both payloads are Closures so only the
     * active theme's data is gathered.
     */
    public function index(): View|InertiaResponse
    {
        /** @var User $user */
        $user = Auth::user();

        // Dual output from one controller (no presenter): the SPA gets the flat,
        // typed DashboardData; Blade keeps its model-rich shape. Each payload is a
        // Closure so only the active theme's data is gathered.
        return response()->themed(
            'Dashboard',
            'dashboard.index',
            bladeData: fn (): array => [
                'user'            => $user,
                'current_airport' => $user->curr_airport_id ?? $user->home_airport_id,
                'last_pirep'      => $user->last_pirep_id
                    ? Pirep::with([
                        'aircraft'    => fn ($q) => $q->withTrashed(),
                        'arr_airport' => fn ($q) => $q->withTrashed(),
                        'comments',
                        'dpt_airport' => fn ($q) => $q->withTrashed(),
                    ])->find($user->last_pirep_id)
                    : null,
            ],
            spa: fn (): DashboardData => DashboardData::fromUser($user),
        );
    }
}

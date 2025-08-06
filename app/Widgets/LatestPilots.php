<?php

namespace App\Widgets;

use App\Contracts\Widget;
use App\Models\Enums\UserState;
use App\Repositories\UserRepository;
use Illuminate\Contracts\View\Factory;
use Illuminate\View\View;

/**
 * Show the latest pilots in a view
 */
class LatestPilots extends Widget
{
    protected $config = [
        'count' => 5,
    ];

    /**
     * @return Factory|View
     */
    public function run()
    {
        $userRepo = app(UserRepository::class);
        $userRepo = $userRepo->with(['airline', 'home_airport'])->where('state', '!=', UserState::DELETED)->orderby('created_at', 'desc')->take($this->config['count'])->get();

        return view('widgets.latest_pilots', [
            'config' => $this->config,
            'users'  => $userRepo,
        ]);
    }
}

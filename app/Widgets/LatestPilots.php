<?php

namespace App\Widgets;

use App\Contracts\Widget;
use App\Models\Enums\UserState;
use App\Repositories\UserRepository;

/**
 * Show the latest pilots in a view
 */
class LatestPilots extends Widget
{
    protected $config = [
        'count' => 5,
    ];

    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
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

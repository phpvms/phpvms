<?php

namespace App\Widgets;

use App\Contracts\Widget;
use App\Models\UserAward;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;

class LatestAwards extends Widget
{
    protected $config = ['count' => 5];

    public function run(): Factory|View
    {
        $latest_awards = UserAward::with(['award', 'user'])->orderby('created_at', 'desc')->take($this->config['count'])->get();

        return view('widgets.latest_awards', [
            'config' => $this->config,
            'awards' => $latest_awards,
        ]);
    }
}

<?php

namespace App\Widgets;

use App\Contracts\Widget;
use App\Repositories\NewsRepository;

/**
 * Show the latest news in a view
 */
class LatestNews extends Widget
{
    protected $config = [
        'count' => 5,
    ];

    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function run()
    {
        $newsRepo = app(NewsRepository::class);

        return view('widgets.latest_news', [
            'config' => $this->config,
            'news'   => $newsRepo->with('user')->recent($this->config['count']),
        ]);
    }
}

<?php

namespace App\Widgets;

use App\Contracts\Widget;
use App\Repositories\NewsRepository;
use Illuminate\Contracts\View\Factory;
use Illuminate\View\View;

/**
 * Show the latest news in a view
 */
class LatestNews extends Widget
{
    protected $config = [
        'count' => 5,
    ];

    /**
     * @return Factory|View
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

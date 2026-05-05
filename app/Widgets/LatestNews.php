<?php

namespace App\Widgets;

use App\Contracts\Widget;
use App\Models\News;
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
    public function run(): Factory|\Illuminate\Contracts\View\View
    {
        return view('widgets.latest_news', [
            'config' => $this->config,
            'news'   => News::with('user')->latest()->paginate($this->config['count']),
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Controller;
use App\Http\Resources\News as NewsResource;
use App\Repositories\NewsRepository;
use Illuminate\Http\Request;

class NewsController extends Controller
{
    /**
     * AirlineController constructor.
     */
    public function __construct(
        private readonly NewsRepository $newsRepo
    ) {
    }

    /**
     * Return all the airlines, paginated.
     *
     *
     * @return mixed
     */
    public function index(Request $request)
    {
        $news = $this->newsRepo
            ->orderBy('created_at', 'desc')
            ->paginate();

        return NewsResource::collection($news);
    }
}

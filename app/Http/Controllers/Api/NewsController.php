<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Controller;
use App\Http\Resources\News as NewsResource;
use App\Models\News;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class NewsController extends Controller
{
    /**
     * Return all the news items, paginated
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $limit = $request->query('limit')
            ? (int) $request->query('limit')
            : config('phpvms.pagination.limit', 50);

        $news = News::with('user')
            ->latest()
            ->paginate($limit)
            ->appends($request->except(['page', 'user']));

        return NewsResource::collection($news);
    }
}

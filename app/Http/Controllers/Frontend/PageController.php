<?php

namespace App\Http\Controllers\Frontend;

use App\Contracts\Controller;
use App\Exceptions\PageNotFound;
use App\Exceptions\Unauthorized;
use App\Models\Page;
use App\Repositories\PageRepository;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PageController extends Controller
{
    public function __construct(
        private readonly PageRepository $pageRepo
    ) {}

    /**
     * Show the page
     */
    public function show(string $slug): View
    {
        /** @var Page $page */
        $page = $this->pageRepo->findWhere(['slug' => $slug])->first();
        if (!$page) {
            throw new PageNotFound(new Exception('Page not found'));
        }

        if (!$page->public && !Auth::check()) {
            throw new Unauthorized(new Exception('You must be logged in to view this page'));
        }

        return view('pages.index', ['page' => $page]);
    }
}

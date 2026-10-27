<?php

namespace App\Http\Controllers\Frontend;

use App\Contracts\Controller;
use App\Enums\PageType;
use App\Exceptions\PageNotFound;
use App\Exceptions\Unauthorized;
use App\Models\Page;
use App\Services\PageService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PageController extends Controller
{
    public function __construct(private readonly PageService $pageService) {}

    /**
     * Show a page by its legacy `page/{slug}` URL.
     *
     * Kept so existing links and bookmarks keep resolving after pages gain
     * claimable paths.
     */
    public function show(string $slug): View
    {
        /** @var ?Page $page */
        $page = Page::bySlug($slug)->live()->where('type', PageType::PAGE)->first();

        if (!$page) {
            throw new PageNotFound(new Exception('Page not found'));
        }

        return $this->render($page);
    }

    /**
     * Show whichever page has claimed the requested path.
     *
     * Reached only through the fallback route, so every registered route has
     * already been tried and lost.
     */
    public function showByPath(Request $request): View
    {
        // The fallback takes every method so unknown URLs stay 404, but a page
        // only answers a read.
        $page = in_array($request->getMethod(), ['GET', 'HEAD'], true)
            ? $this->pageService->resolve($request->path())
            : null;

        if (!$page instanceof Page) {
            throw new PageNotFound(new Exception('Page not found'));
        }

        return $this->render($page);
    }

    /**
     * Render the draft of a page for an authorised author.
     *
     * Reuses the live page view with the draft swapped in for the body. The
     * swap is in memory only and the model is never saved.
     */
    public function preview(Page $page): View
    {
        $page->setAttribute('body', $page->draft_body);

        return view('pages.index', ['page' => $page]);
    }

    /**
     * The visibility gate, unchanged: public pages are open, everything else
     * needs a session. Reaching this point already means the page is live.
     */
    private function render(Page $page): View
    {
        if (!$page->public && !Auth::check()) {
            throw new Unauthorized(new Exception('You must be logged in to view this page'));
        }

        return view('pages.index', ['page' => $page]);
    }
}

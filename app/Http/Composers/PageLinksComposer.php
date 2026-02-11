<?php

namespace App\Http\Composers;

use App\Contracts\Composer;
use App\Repositories\PageRepository;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PageLinksComposer extends Composer
{
    public static array $fields = ['id', 'name', 'slug', 'icon', 'type', 'link', 'new_window'];

    /**
     * PageLinksComposer constructor.
     */
    public function __construct(
        private readonly PageRepository $pageRepo
    ) {}

    public function compose(View $view)
    {
        try {
            $w = [
                'enabled' => true,
            ];

            // If not logged in, then only get the public pages
            if (!Auth::check()) {
                $w['public'] = true;
            }

            $pages = $this->pageRepo->findWhere($w, static::$fields);
        } catch (Exception $e) {
            $pages = [];
        }

        $view->with('page_links', $pages);
    }
}

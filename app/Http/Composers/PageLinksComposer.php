<?php

declare(strict_types=1);

namespace App\Http\Composers;

use App\Contracts\Composer;
use App\Models\Page;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PageLinksComposer extends Composer
{
    public static array $fields = ['id', 'name', 'slug', 'icon', 'type', 'link', 'new_window'];

    public function compose(View $view): void
    {
        try {
            $w = [
                'enabled' => true,
            ];

            // If not logged in, then only get the public pages
            if (!Auth::check()) {
                $w['public'] = true;
            }

            $pages = Page::where($w)->get(static::$fields);
        } catch (Exception) {
            $pages = [];
        }

        $view->with('page_links', $pages);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Composers;

use App\Contracts\Composer;
use App\Enums\PageStatus;
use App\Enums\PageType;
use App\Models\Page;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PageLinksComposer extends Composer
{
    public static array $fields = ['id', 'name', 'slug', 'path', 'icon', 'type', 'link', 'new_window'];

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

            // A draft or unpublished page would link to a 404. Links have no
            // content to publish, so they are always listed.
            $pages = Page::where($w)
                ->where(fn ($q) => $q->where('type', PageType::LINK)->orWhere('status', PageStatus::PUBLISHED))
                ->get(static::$fields);
        } catch (Exception) {
            $pages = [];
        }

        $view->with('page_links', $pages);
    }
}

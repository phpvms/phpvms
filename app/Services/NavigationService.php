<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Service;
use App\Enums\NavigationTarget;
use App\Enums\PageType;
use App\Models\NavigationItem;
use App\Models\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Route;

/**
 * Builds the site menu for the SPA shell.
 */
class NavigationService extends Service
{
    /**
     * What a menu icon may look like: a bare icon name (`layout-dashboard`,
     * `i-tabler-plane`, `PlaneArrival`), never markup. The SPA renders values
     * starting with `<` as raw HTML, so anything else must not reach it.
     */
    public const string ICON_PATTERN = '/^[A-Za-z0-9_:-]+$/D';

    /**
     * The menu a viewer should see, ordered and nested.
     *
     * Items are dropped rather than rendered broken when their target has gone:
     * an unpublished page or a route that no longer exists would otherwise put a
     * guaranteed 404 in the navigation.
     *
     * @return list<array{label: string, url: string, icon: string|null, new_window: bool, spa: bool, children: list<mixed>}>
     */
    public function menu(bool $authenticated): array
    {
        $items = NavigationItem::query()
            ->visibleTo($authenticated)
            ->ordered()
            ->get();

        if ($items->isEmpty()) {
            return [];
        }

        return $this->build($items, $this->pageUrls($items), null);
    }

    /**
     * Every page URL the menu needs, in one query rather than one per item.
     *
     * Keyed by integer id: PHP casts numeric string array keys to int anyway,
     * so declaring string keys would only mislead the reader.
     *
     * @param  Collection<int, NavigationItem> $items
     * @return array<int, string>
     */
    private function pageUrls(Collection $items): array
    {
        $ids = $items
            ->where('target_type', NavigationTarget::PAGE)
            ->pluck('target_value')
            ->filter()
            ->all();

        if ($ids === []) {
            return [];
        }

        return Page::query()
            ->live()
            ->where('type', PageType::PAGE)
            ->whereIn('id', $ids)
            ->get()
            ->mapWithKeys(fn (Page $page): array => [(int) $page->id => (string) $page->url])
            ->all();
    }

    /**
     * @param  Collection<int, NavigationItem>                                                                                $items
     * @param  array<int, string>                                                                                             $pageUrls
     * @return list<array{label: string, url: string, icon: string|null, new_window: bool, spa: bool, children: list<mixed>}>
     */
    private function build(Collection $items, array $pageUrls, ?int $parentId): array
    {
        $branch = [];

        foreach ($items->where('parent_id', $parentId) as $item) {
            $children = $this->build($items, $pageUrls, (int) $item->id);
            $url = $this->urlFor($item, $pageUrls);

            // A dead target is still worth keeping if it groups live children.
            if ($url === null && $children === []) {
                continue;
            }

            $branch[] = [
                'label'      => $this->labelFor($item),
                'url'        => $url ?? '',
                'icon'       => $this->iconFor($item),
                'new_window' => $item->new_window,
                // Route and page targets are in-app visits; a URL target may
                // point anywhere, so it is a plain link.
                'spa'      => $item->target_type !== NavigationTarget::URL,
                'children' => $children,
            ];
        }

        return $branch;
    }

    /**
     * The label as the viewer should read it.
     *
     * A label is first checked as a translation key, so the seeded built-in
     * items (`common.dashboard`, `ui.nav_flights`, ...) follow the site
     * language. Anything that is not a known key is shown exactly as typed.
     *
     * Only a dotted `group.key` counts, and only when it translates to a
     * string: a bare word like "Fleet" names a whole lang file, which `__()`
     * returns as an array.
     */
    private function labelFor(NavigationItem $item): string
    {
        $label = $item->label;

        if (!str_contains($label, '.') || !Lang::has($label)) {
            return $label;
        }

        $translated = __($label);

        return is_string($translated) ? $translated : $label;
    }

    /**
     * The icon name, or null when the stored value is not a plain name.
     */
    private function iconFor(NavigationItem $item): ?string
    {
        $icon = $item->icon;

        return is_string($icon) && preg_match(self::ICON_PATTERN, $icon) === 1 ? $icon : null;
    }

    /**
     * @param array<int, string> $pageUrls
     */
    private function urlFor(NavigationItem $item, array $pageUrls): ?string
    {
        if (blank($item->target_value)) {
            return null;
        }

        return match ($item->target_type) {
            NavigationTarget::URL   => $item->target_value,
            NavigationTarget::PAGE  => $pageUrls[(int) $item->target_value] ?? null,
            NavigationTarget::ROUTE => Route::has($item->target_value)
                ? route($item->target_value)
                : null,
        };
    }
}

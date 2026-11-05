<?php

declare(strict_types=1);

namespace App\Filament\GlobalSearch;

use Filament\Facades\Filament;
use Filament\GlobalSearch\GlobalSearchResult;
use Filament\GlobalSearch\GlobalSearchResults;
use Filament\GlobalSearch\Providers\Contracts\GlobalSearchProvider;
use Filament\GlobalSearch\Providers\DefaultGlobalSearchProvider;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Resources\Resource;
use Illuminate\Contracts\Support\Htmlable;
use Override;
use UnitEnum;

/**
 * Global search for the command palette: the panel's own navigation pages
 * ("Go to") first, then "Add <thing>" create actions, then the record
 * categories Filament's default provider finds.
 */
final class CommandPaletteSearchProvider implements GlobalSearchProvider
{
    #[Override]
    public function getResults(string $query): GlobalSearchResults
    {
        $results = GlobalSearchResults::make();

        $pages = $this->matchingPages($query);

        if ($pages !== []) {
            $results->category(__('filament.palette_go_to'), $pages);
        }

        $actions = $this->matchingActions($query);

        if ($actions !== []) {
            $results->category(__('filament.palette_actions'), $actions);
        }

        $records = new DefaultGlobalSearchProvider()->getResults($query);

        foreach ($records->getCategories() as $name => $categoryResults) {
            $results->category($name, $categoryResults);
        }

        return $results;
    }

    /**
     * Navigation items the current user can see, whose label or group label
     * contains the query. Filament's navigation build already drops items the
     * user has no access to.
     *
     * @return array<int, GlobalSearchResult>
     */
    private function matchingPages(string $query): array
    {
        $matches = [];

        foreach (Filament::getCurrentOrDefaultPanel()->getNavigation() as $group) {
            $groupLabel = $this->text($group->getLabel());

            foreach ($this->flattenItems($group) as $item) {
                $url = $item->getUrl();
                $label = $this->text($item->getLabel());

                if (blank($url) || $label === '') {
                    continue;
                }

                if (!str_contains(mb_strtolower($label.' '.$groupLabel), mb_strtolower($query))) {
                    continue;
                }

                $matches[] = new GlobalSearchResult(
                    title: $label,
                    url: $url,
                    details: $groupLabel === '' ? [] : ['group' => $groupLabel],
                );
            }
        }

        return $matches;
    }

    /**
     * Create actions for top-level resources the user may create records for.
     * Every word of the query must appear in "add new create {model labels}".
     *
     * @return array<int, GlobalSearchResult>
     */
    private function matchingActions(string $query): array
    {
        $words = preg_split('/\s+/', mb_strtolower(trim($query)), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($words === []) {
            return [];
        }

        $matches = [];

        /** @var class-string<resource> $resource */
        foreach (Filament::getCurrentOrDefaultPanel()->getResources() as $resource) {
            if ($resource::getParentResourceRegistration() !== null || !$resource::hasPage('create') || !$resource::canCreate()) {
                continue;
            }

            $label = $resource::getModelLabel();
            $haystack = mb_strtolower('add new create '.$label.' '.$resource::getPluralModelLabel());

            foreach ($words as $word) {
                if (!str_contains($haystack, $word)) {
                    continue 2;
                }
            }

            $group = $resource::getNavigationGroup();
            $groupLabel = $group instanceof UnitEnum && method_exists($group, 'getLabel')
                ? $this->text($group->getLabel())
                : (is_string($group) ? $group : '');

            $matches[] = new GlobalSearchResult(
                title: __('filament.palette_create', ['label' => $label]),
                url: $resource::getUrl('create'),
                details: $groupLabel === '' ? [] : ['group' => $groupLabel],
            );
        }

        return $matches;
    }

    /**
     * @return array<int, NavigationItem>
     */
    private function flattenItems(NavigationGroup $group): array
    {
        $items = [];

        foreach ($group->getItems() as $item) {
            $items[] = $item;

            foreach ($item->getChildItems() as $child) {
                $items[] = $child;
            }
        }

        return $items;
    }

    private function text(string|Htmlable|null $value): string
    {
        return $value instanceof Htmlable ? strip_tags($value->toHtml()) : (string) $value;
    }
}

<?php

declare(strict_types=1);

namespace App\Livewire\Filament;

use Filament\Facades\Filament;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Command palette overlay (cmd/ctrl+K). Rendered once per page on
 * PanelsRenderHook::BODY_END; the open/close and keyboard behaviour live in
 * resources/js/apps/admin/command-palette.js.
 */
final class CommandPalette extends Component
{
    public string $query = '';

    /**
     * Results of the current panel's global search provider, flattened for the view.
     *
     * @return array<string, array<int, array{title: string, url: string, details: string}>>
     */
    public function groups(): array
    {
        $search = trim($this->query);

        if ($search === '') {
            return [];
        }

        $results = Filament::getCurrentOrDefaultPanel()->getGlobalSearchProvider()?->getResults($search);

        $groups = [];

        foreach ($results?->getCategories() ?? [] as $category => $categoryResults) {
            foreach ($categoryResults as $result) {
                $title = $result->title;
                $groups[(string) $category][] = [
                    'title'   => $title instanceof Htmlable ? strip_tags($title->toHtml()) : $title,
                    'url'     => $result->url,
                    'details' => implode(' · ', array_filter(array_map(strval(...), $result->details))),
                ];
            }
        }

        return $groups;
    }

    public function render(): View
    {
        return view('livewire.filament.command-palette', [
            'groups' => $this->groups(),
            'isSpa'  => Filament::getCurrentOrDefaultPanel()->hasSpaMode(),
        ]);
    }
}

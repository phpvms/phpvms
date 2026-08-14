<?php

namespace App\View\Components\Admin;

use Filament\Actions\Action;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * The carded read-only header for edit pages whose real content is the
 * record's tables (identity → workspace → tucked-away settings; same
 * .overview pattern as the PIREP detail header and the dashboard stats
 * strip).
 *
 * The optional edit action renders inside the last card's top-right corner
 * and pairs with App\Filament\Actions\EditDetailsAction — the branded
 * settings drawer that edits the identity the cards display.
 *
 * Blade pages render the tag directly:
 *
 *     <x-admin.overview :cards="$this->summaryCards()" aria-label="..."
 *         :edit-action="$this->editAction" />
 *
 * Filament schemas point at the same view, so both paths share one file:
 *
 *     View::make('components.admin.overview')->viewData([
 *         'cards' => ..., 'ariaLabel' => ..., 'editAction' => $this->editAction,
 *     ])
 */
class Overview extends Component
{
    /**
     * @param array<int, array{icon: mixed, tint?: string|null, label: string, value: string, note?: string|null}> $cards
     */
    public function __construct(
        public array $cards,
        public string $ariaLabel,
        public ?Action $editAction = null,
    ) {}

    public function render(): View
    {
        return view('components.admin.overview');
    }
}

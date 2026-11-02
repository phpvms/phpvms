<?php

namespace App\View\Components\Admin;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Folder tabs on the banner's bottom edge, for custom pages that cannot use
 * Filament's own tabs. Filament tab strips directly under the page header get
 * the identical look from the same theme.css rules (".banner-tab" and
 * ".fi-tabs-item" are one definition there).
 *
 * The page must reserve the strip under the banner (the header's bottom
 * padding); the strip then pulls itself up over it.
 *
 * Two ways to switch, mixable per tab:
 *
 *  - Alpine: pass `model`, the name of the Alpine property in an enclosing
 *    x-data scope that holds the active key. Tabs without a `url` set it on
 *    click.
 *  - Links: give a tab a `url`, and mark the current one with `active`.
 *
 *     <x-admin.banner-tabs model="activeTab" aria-label="Report sections" :tabs="[
 *         ['key' => 'flight', 'label' => 'Flight', 'icon' => 'phosphor-airplane-light'],
 *         ['key' => 'log', 'label' => 'Log', 'icon' => 'phosphor-list-light', 'badge' => 12,
 *             'panel' => 'tab-log'],
 *     ]" />
 *
 * Each tab's button id is `t-{key}`; `panel` is the id of the panel it controls.
 */
class BannerTabs extends Component
{
    /**
     * @param array<int, array{key: string, label: string, icon?: string|null, badge?: string|int|null, url?: string|null, panel?: string|null}> $tabs
     */
    public function __construct(
        public array $tabs,
        public ?string $model = null,
        public ?string $active = null,
        public ?string $ariaLabel = null,
    ) {}

    public function render(): View
    {
        return view('components.admin.banner-tabs');
    }
}

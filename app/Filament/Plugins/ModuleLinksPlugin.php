<?php

namespace App\Filament\Plugins;

use App\Services\ModuleService;
use Filament\Contracts\Plugin;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\Factory;

class ModuleLinksPlugin implements Plugin
{
    public function getId(): string
    {
        return 'module-links';
    }

    public static function make(): static
    {
        return app(static::class);
    }

    public function register(Panel $panel): void
    {
        // Render in the topbar (wide screen)
        $panel->renderHook(PanelsRenderHook::TOPBAR_LOGO_AFTER, fn (): Factory|\Illuminate\Contracts\View\View => view('filament.plugins.module-links-topbar', [
            'current_panel' => Filament::getCurrentOrDefaultPanel(),
            'group'         => $this->getGroup(),
        ]));

        // Render in the sidebar (mobile)
        $panel->renderHook(PanelsRenderHook::SIDEBAR_NAV_END, fn (): Factory|\Illuminate\Contracts\View\View => view('filament.plugins.module-links-sidebar', [
            'group' => $this->getGroup(),
        ]));
    }

    public function boot(Panel $panel): void
    {
        //
    }

    private function getGroup(): NavigationGroup
    {
        $items = [];

        $panels = Filament::getPanels();
        foreach ($panels as $panel) {
            if ($panel->getId() === 'admin') {
                continue;
            }

            if ($panel->getId() === 'system') {
                continue;
            }

            $panel_name = ucfirst(str_replace('::admin', '', $panel->getId()));
            $items[] = NavigationItem::make($panel_name)
                ->icon(Heroicon::OutlinedPuzzlePiece)
                ->url(url($panel->getPath()));
        }

        $old_links = array_filter(app(ModuleService::class)->getAdminLinks(), static fn (array $link): bool => !str_contains((string) $link['title'], 'Sample'));
        foreach ($old_links as $link) {
            $items[] = NavigationItem::make($link['title'])
                ->url($link['url'])
                ->icon(Heroicon::OutlinedFolder);
        }

        $group = \App\Enums\NavigationGroup::Modules;

        return NavigationGroup::make($group->name)
            ->label($group->getLabel())
            ->items($items);
    }
}

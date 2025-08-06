<?php

namespace App\Filament\Plugins;

use App\Services\ModuleService;
use Filament\Contracts\Plugin;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Panel;

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
        $panel->renderHook('panels::topbar.start', function () {
            return view('filament.plugins.module-links-topbar', [
                'current_panel' => Filament::getCurrentOrDefaultPanel(),
                'group'         => $this->getGroup(),
            ]);
        });

        // Render in the sidebar (mobile)
        $panel->renderHook('panels::sidebar.nav.end', function () {
            return view('filament.plugins.module-links-sidebar', [
                'group' => $this->getGroup(),
            ]);
        });
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
            if ($panel->getId() === 'admin' || $panel->getId() === 'system') {
                continue;
            }

            $panel_name = ucfirst(str_replace('::admin', '', $panel->getId()));
            $items[] = NavigationItem::make($panel_name)
                ->icon('heroicon-o-puzzle-piece')
                ->url(url($panel->getPath()));
        }

        $old_links = array_filter(app(ModuleService::class)->getAdminLinks(), static fn (array $link): bool => !str_contains($link['title'], 'Sample'));
        foreach ($old_links as $link) {
            $items[] = NavigationItem::make($link['title'])
                ->url($link['url'])
                ->icon('heroicon-o-folder');
        }

        return NavigationGroup::make('Modules')
            ->items($items);
    }
}

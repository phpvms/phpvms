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
            $group = $this->getGroup();

            return view('filament.plugins.module-links-topbar', [
                'current_panel' => Filament::getCurrentPanel(),
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
}

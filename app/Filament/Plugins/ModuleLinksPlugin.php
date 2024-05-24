<?php

namespace App\Filament\Plugins;

use App\Services\ModuleService;
use Filament\Contracts\Plugin;
use Filament\Facades\Filament;
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
        $panel->renderHook('panels::topbar.start', function () {
            return view('filament.plugins.module-links', [
                'current_panel' => Filament::getCurrentPanel(),
                'panels'        => Filament::getPanels(),
                'old_links'     => array_filter(app(ModuleService::class)->getAdminLinks(), static fn (array $link): bool => !str_contains($link['title'], 'Sample')),
            ]);
        });
    }

    public function boot(Panel $panel): void
    {
        //
    }
}

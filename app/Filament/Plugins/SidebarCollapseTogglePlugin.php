<?php

declare(strict_types=1);

namespace App\Filament\Plugins;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;

/**
 * Renders a collapse/expand button at the bottom of the sidebar instead of
 * relying on Filament's default hamburger in the topbar. The default topbar
 * button is hidden via CSS (see resources/css/filament/admin/theme.css).
 */
final class SidebarCollapseTogglePlugin implements Plugin
{
    public static function make(): self
    {
        return new self();
    }

    public function getId(): string
    {
        return 'sidebar-collapse-toggle';
    }

    public function register(Panel $panel): void
    {
        $panel->renderHook(
            PanelsRenderHook::SIDEBAR_FOOTER,
            fn (): View => view('filament.plugins.sidebar-collapse-toggle'),
        );
    }

    public function boot(Panel $panel): void
    {
        //
    }
}

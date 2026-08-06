<?php

declare(strict_types=1);

namespace App\Filament\Plugins;

use Filament\Contracts\Plugin;
use Filament\Facades\Filament;
use Filament\Panel;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
use Modules\AddonManager\Filament\Pages\Addons;
use Throwable;

/**
 * Renders a panel-switcher dropdown at the left of the topbar.
 *
 * Registered on the main admin panel and on every module panel (via the base
 * module panel-provider contract), it lists the main panel plus each module
 * panel the current user may access, so the user can switch context and return.
 *
 * The `system` panel is excluded — it is an internal phpVMS panel, not a
 * context a user navigates to from the switcher.
 *
 * Stateless and Octane-safe: no mutable instance state; the panel list is
 * resolved per request from the Filament registry.
 */
final class PanelSwitcherPlugin implements Plugin
{
    public static function make(): self
    {
        return new self();
    }

    public function getId(): string
    {
        return 'panel-switcher';
    }

    public function register(Panel $panel): void
    {
        $panel->renderHook(
            PanelsRenderHook::TOPBAR_LOGO_AFTER,
            fn (): View => $this->renderSwitcher(),
        );
    }

    public function boot(Panel $panel): void
    {
        //
    }

    /**
     * Build the switcher view with the panels the current user can access.
     */
    private function renderSwitcher(): View
    {
        $user = auth()->user();
        $current = Filament::getCurrentOrDefaultPanel();

        $panels = [];

        foreach (Filament::getPanels() as $panel) {
            if ($panel->getId() === 'system') {
                continue;
            }

            // Only show panels the user is permitted to access; mirrors the
            // gate Filament itself applies via User::canAccessPanel().
            if ($user !== null && !$user->canAccessPanel($panel)) {
                continue;
            }

            $panels[] = $panel;
        }

        // The admin panel always leads the menu; the rest keep registry order.
        usort($panels, static fn (Panel $a, Panel $b): int => ($b->getId() === 'admin') <=> ($a->getId() === 'admin'));

        return view('filament.plugins.panel-switcher', [
            'panels'    => $panels,
            'current'   => $current,
            'brandName' => config('app.name') ?: 'phpvms',
            'addonsUrl' => $this->addonsUrl(),
        ]);
    }

    /**
     * URL of the addon manager page, or null when the module isn't
     * installed or the current user can't access it.
     */
    private function addonsUrl(): ?string
    {
        try {
            if (!class_exists(Addons::class) || !Addons::canAccess()) {
                return null;
            }

            return Addons::getUrl();
        } catch (Throwable) {
            return null;
        }
    }
}

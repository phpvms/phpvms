<?php

declare(strict_types=1);

namespace App\Filament\Plugins;

use App\Addons\Support\AddonIcon;
use App\Addons\Support\BootCache;
use App\Support\Branding;
use Filament\Contracts\Plugin;
use Filament\Facades\Filament;
use Filament\Panel;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
use Modules\AddonManager\Filament\Pages\Addons;
use Throwable;

/**
 * Renders a panel-switcher dropdown in the 44px brand band at the top of the
 * sidebar, which lines up with the topbar so the two read as one bar.
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
            PanelsRenderHook::SIDEBAR_START,
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
            'brandName' => app(Branding::class)->name(),
            // 64px is the switcher/table icon derivative. Null when the airline
            // has uploaded nothing, so the blade keeps the built-in mark and an
            // unconfigured install looks exactly as it did.
            'brandMark' => app(Branding::class)->hasLogo()
                ? app(Branding::class)->logo(64)
                : null,
            'addonsUrl'  => $this->addonsUrl(),
            'panelIcons' => $this->panelIcons($panels),
        ]);
    }

    /**
     * The manifest icon URL of each addon panel, keyed by panel id. An addon's
     * panel id is its manifest alias, else its lowercased folder name. Read
     * from the boot cache, so it costs no query.
     *
     * @param  list<Panel>           $panels
     * @return array<string, string>
     */
    private function panelIcons(array $panels): array
    {
        $ids = array_map(static fn (Panel $panel): string => $panel->getId(), $panels);
        $icons = [];

        foreach (app(BootCache::class)->enabled() as $entry) {
            $id = $entry->alias !== null && $entry->alias !== '' ? $entry->alias : strtolower(basename($entry->path));

            if (!in_array($id, $ids, true)) {
                continue;
            }

            $url = AddonIcon::url($entry->name, $entry->path, $entry->icon);

            if ($url !== null) {
                $icons[$id] = $url;
            }
        }

        return $icons;
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

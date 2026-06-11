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
use Illuminate\Contracts\View\View;

class ModuleLinksPlugin implements Plugin
{
    /**
     * Panel ids that have already had legacy links appended, keyed for
     * idempotency. The plugin instance lives on the Panel, so this persists
     * per Octane worker (append once) but resets on a fresh app boot.
     *
     * @var array<string, true>
     */
    private array $registeredPanels = [];

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
        // Topbar (wide screen): links to *other* Filament panels only. Legacy
        // addAdminLink() links now render as native left-sidebar items below.
        $panel->renderHook(PanelsRenderHook::TOPBAR_LOGO_AFTER, fn (): Factory|View => view('filament.plugins.module-links-topbar', [
            'current_panel' => Filament::getCurrentOrDefaultPanel(),
            'group'         => $this->getPanelGroup(),
        ]));

        // Backwards compatibility: surface legacy addAdminLink() links as native
        // sidebar nav items under the AddOns group, alongside addon Filament
        // resources. Deferred to serving() because the links are populated during
        // module boot(), which runs after the panel is configured. The static
        // guard keeps the append idempotent per worker (Octane-safe).
        Filament::serving(function () use ($panel): void {
            if (isset($this->registeredPanels[$id = $panel->getId()])) {
                return;
            }

            $this->registeredPanels[$id] = true;

            $panel->navigationItems($this->legacyNavigationItems());
        });
    }

    public function boot(Panel $panel): void
    {
        //
    }

    /**
     * Topbar group of links to other registered Filament panels, excluding the
     * admin and system panels themselves.
     */
    private function getPanelGroup(): NavigationGroup
    {
        $items = [];

        foreach (Filament::getPanels() as $panel) {
            if (in_array($panel->getId(), ['admin', 'system'], true)) {
                continue;
            }

            $items[] = NavigationItem::make(ucfirst(str_replace('::admin', '', $panel->getId())))
                ->icon(Heroicon::OutlinedPuzzlePiece)
                ->url(url($panel->getPath()));
        }

        $group = \App\Enums\NavigationGroup::AddOns;

        return NavigationGroup::make($group->name)
            ->label($group->getLabel())
            ->items($items);
    }

    /**
     * Legacy addAdminLink() links as native sidebar nav items under the AddOns
     * group. Sample is excluded — it ships a Filament resource instead.
     *
     * @return list<NavigationItem>
     */
    private function legacyNavigationItems(): array
    {
        // Pass the enum (not ->name): NavigationManager buckets items by the
        // serialized group, so a string would land in a separate group from the
        // addon Filament resources (which use the enum) and render the raw
        // "AddOns" label instead of the enum's "Add-Ons" getLabel().
        $group = \App\Enums\NavigationGroup::AddOns;

        $links = array_filter(
            app(ModuleService::class)->getAdminLinks(),
            static fn (array $link): bool => !str_contains((string) $link['title'], 'Sample'),
        );

        return array_values(array_map(
            static fn (array $link): NavigationItem => NavigationItem::make($link['title'])
                ->group($group)
                ->icon(Heroicon::OutlinedFolder)
                ->url($link['url'])
                ->visible(fn (): bool => auth()->user()?->can('view:modules') ?? false)
                ->isActiveWhen(function () use ($link): bool {
                    $path = trim((string) parse_url((string) $link['url'], PHP_URL_PATH), '/');

                    return $path !== '' && request()->is($path, $path.'/*');
                }),
            $links,
        ));
    }
}

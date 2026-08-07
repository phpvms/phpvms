<?php

namespace App\Providers\Filament;

use App\Addons\Support\BootCache;
use App\Enums\NavigationGroup as EnumsNavigationGroup;
use App\Filament\Pages\Backups;
use Daljo25\FilamentTablerIcons\Enums\TablerIcon;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\Support\Assets\AlpineComponent;
use Filament\Support\Assets\Css;
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentAsset;
use Filament\Widgets\AccountWidget;
use ShuvroRoy\FilamentSpatieLaravelBackup\FilamentSpatieLaravelBackupPlugin;

class AdminPanelProvider extends BasePanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $panel = $this->applyConsoleChrome($panel)
            ->default()
            ->id('admin')
            ->path('admin')
            ->colors([
                'primary' => Color::generatePalette('#067ec1'),
            ])
            ->assets([
                Css::make('leaflet', 'https://unpkg.com/leaflet@1.7.1/dist/leaflet.css'),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->discoverClusters(in: app_path('Filament/Clusters'), for: 'App\\Filament\\Clusters')
            ->pages([])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            // Icons turn the collapsed desktop sidebar into a module rail:
            // Filament renders the group icon instead of its items, and opens
            // the items in a dropdown beside it. That is the console's rail
            // plus its flyout menu, with no custom markup.
            // The module rail, in order. Dashboard stays ungrouped so it sits
            // on the rail as its own item rather than inside a module.
            // Keyed by enum case name: NavigationManager matches an item's group
            // against the registered array key first, and only falls back to
            // comparing translated labels. Bare (numerically-indexed) entries hit
            // that fallback and stop matching under any locale whose label differs
            // from the case name, which silently drops the icon.
            ->navigationGroups([
                EnumsNavigationGroup::Operations->name => NavigationGroup::make()
                    ->label(fn (): string => EnumsNavigationGroup::Operations->getLabel())
                    ->icon(TablerIcon::Plane),
                EnumsNavigationGroup::Planning->name => NavigationGroup::make()
                    ->label(fn (): string => EnumsNavigationGroup::Planning->getLabel())
                    ->icon(TablerIcon::CalendarTime),
                EnumsNavigationGroup::Fleet->name => NavigationGroup::make()
                    ->label(fn (): string => EnumsNavigationGroup::Fleet->getLabel())
                    ->icon(TablerIcon::Box),
                EnumsNavigationGroup::Pilots->name => NavigationGroup::make()
                    ->label(fn (): string => EnumsNavigationGroup::Pilots->getLabel())
                    ->icon(TablerIcon::Users),
                EnumsNavigationGroup::Finance->name => NavigationGroup::make()
                    ->label(fn (): string => EnumsNavigationGroup::Finance->getLabel())
                    ->icon(TablerIcon::Cash),
                EnumsNavigationGroup::Config->name => NavigationGroup::make()
                    ->label(fn (): string => EnumsNavigationGroup::Config->getLabel())
                    ->icon(TablerIcon::Settings),
                EnumsNavigationGroup::AddOns->name => NavigationGroup::make()
                    ->label(fn (): string => EnumsNavigationGroup::AddOns->getLabel())
                    ->icon(TablerIcon::Puzzle),
                EnumsNavigationGroup::System->name => NavigationGroup::make()
                    ->label(fn (): string => EnumsNavigationGroup::System->getLabel())
                    ->icon(TablerIcon::Terminal),
            ])
            ->navigationItems([
                // Labels should be in a closure to allow for translation

                NavigationItem::make()
                    ->visible(fn (): bool => auth()->user()?->can('view-logs') ?? false)
                    ->group(EnumsNavigationGroup::System)
                    ->sort(3)
                    ->icon(TablerIcon::FileText)
                    ->label(fn (): string => __('common.view_logs'))
                    ->url(config('log-viewer.route_path')),
            ])
            // Base plugins (panel switcher, caches, language) come from
            // applyConsoleChrome(); plugins() is additive.
            ->plugins([
                FilamentSpatieLaravelBackupPlugin::make()
                    ->usingPage(Backups::class),
            ]);

        return $this->discoverModuleComponents($panel);
    }

    /**
     * Discover Filament pages/resources/widgets contributed by each enabled
     * module into the main admin panel.
     *
     * A module's PSR-4 root (`{module}/app`, namespace e.g. `Modules\Foo`) is
     * read from the boot cache — the same DB-free source `AddonAutoLoader` uses,
     * which is already registered before panel boot — so its
     * `app/Filament/{Pages,Resources,Widgets}` directories map to
     * `Modules\Foo\Filament\{Pages,Resources,Widgets}`. Disabled modules are
     * absent from `enabled()` and contribute nothing. Discovery is additive and
     * baked into `filament:cache-components` like the core paths above.
     *
     * Modules that ship their OWN Filament panel (they declare a `*PanelProvider`
     * in their manifest — e.g. ACARS, VACentral) are skipped: that provider
     * already discovers the same `app/Filament/*` directories into its own
     * gated sub-panel, so contributing them to the main panel too would
     * duplicate the nav and, worse, bypass the sub-panel's `access:{moduleKey}`
     * gate. Main-panel contribution is therefore reserved for modules without a
     * panel of their own (the addon manager).
     */
    private function discoverModuleComponents(Panel $panel): Panel
    {
        foreach (app(BootCache::class)->enabled() as $entry) {
            $namespace = rtrim($entry->namespace, '\\');
            $base = rtrim($entry->autoloadPath, '/');
            if ($namespace === '') {
                continue;
            }

            if ($base === '') {
                continue;
            }

            $shipsOwnPanel = collect($entry->providers)
                ->contains(fn (string $provider): bool => str_ends_with($provider, 'PanelProvider'));

            if ($shipsOwnPanel) {
                continue;
            }

            $filament = $base.'/Filament';

            if (is_dir($filament.'/Resources')) {
                $panel->discoverResources(in: $filament.'/Resources', for: $namespace.'\\Filament\\Resources');
            }

            if (is_dir($filament.'/Pages')) {
                $panel->discoverPages(in: $filament.'/Pages', for: $namespace.'\\Filament\\Pages');
            }

            if (is_dir($filament.'/Widgets')) {
                $panel->discoverWidgets(in: $filament.'/Widgets', for: $namespace.'\\Filament\\Widgets');
            }
        }

        return $panel;
    }

    public function boot(): void
    {
        // AlpineComponent assets are esbuild-built standalone files in
        // resources/js/dist/admin/components/ (see bin/build.js) and are
        // referenced via FilamentAsset::getAlpineComponentSrc() in blade.
        // These files do not go through Vite, so registering them at boot
        // is safe: no manifest lookup, no console crash on fresh checkout.
        FilamentAsset::register([
            AlpineComponent::make(
                'pirep-performance-chart',
                resource_path('js/dist/admin/components/pirep-performance-chart.js'),
            ),
            AlpineComponent::make(
                'pirep-landing-analysis',
                resource_path('js/dist/admin/components/pirep-landing-analysis.js'),
            ),
        ]);

        // Expose map-related config to JS (window.filamentData.maps).
        // The OpenAIP overlay needs an API key client-side — pulling from
        // config keeps it out of the bundled JS and lets each install
        // configure its own key in .env.
        FilamentAsset::registerScriptData([
            'maps' => [
                'openaip_api_key' => config('services.openaip.api_key'),
            ],
        ]);
    }
}

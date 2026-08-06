<?php

namespace App\Providers\Filament;

use App\Addons\Support\BootCache;
use App\Enums\NavigationGroup as EnumsNavigationGroup;
use App\Filament\Pages\Backups;
use App\Filament\Plugins\ClearCachesPlugin;
use App\Filament\Plugins\LanguageSwitcherPlugin;
use App\Filament\Plugins\PanelSwitcherPlugin;
use Filament\Enums\UserMenuPosition;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Assets\AlpineComponent;
use Filament\Support\Assets\Css;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use ShuvroRoy\FilamentSpatieLaravelBackup\FilamentSpatieLaravelBackupPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $panel = $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
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
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->sidebarCollapsibleOnDesktop()
            ->sidebarWidth('14.5rem')
            // Rail-foot avatar, not a topbar dropdown — the console rail is
            // the one persistent chrome element, so account access lives
            // there instead of in the bar that scrolls out of memory.
            ->userMenu(position: UserMenuPosition::Sidebar)
            // 76px collapsed — the console rail width, wide enough for the
            // module label the theme draws under each icon.
            ->collapsedSidebarWidth('4.75rem')
            ->maxContentWidth(Width::Full)
            // Icons turn the collapsed desktop sidebar into a module rail:
            // Filament renders the group icon instead of its items, and opens
            // the items in a dropdown beside it. That is the console's rail
            // plus its flyout menu, with no custom markup.
            // The module rail, in order. Dashboard stays ungrouped so it sits
            // on the rail as its own item rather than inside a module.
            ->navigationGroups([
                NavigationGroup::make()
                    ->label(fn (): string => EnumsNavigationGroup::Operations->getLabel())
                    ->icon(Heroicon::OutlinedPaperAirplane),
                NavigationGroup::make()
                    ->label(fn (): string => EnumsNavigationGroup::Planning->getLabel())
                    ->icon(Heroicon::OutlinedCalendarDays),
                NavigationGroup::make()
                    ->label(fn (): string => EnumsNavigationGroup::Fleet->getLabel())
                    ->icon(Heroicon::OutlinedCube),
                NavigationGroup::make()
                    ->label(fn (): string => EnumsNavigationGroup::Pilots->getLabel())
                    ->icon(Heroicon::OutlinedUsers),
                NavigationGroup::make()
                    ->label(fn (): string => EnumsNavigationGroup::Finance->getLabel())
                    ->icon(Heroicon::OutlinedBanknotes),
                NavigationGroup::make()
                    ->label(fn (): string => EnumsNavigationGroup::Config->getLabel())
                    ->icon(Heroicon::OutlinedCog6Tooth),
                NavigationGroup::make()
                    ->label(fn (): string => EnumsNavigationGroup::System->getLabel())
                    ->icon(Heroicon::OutlinedCommandLine),
            ])
            ->navigationItems([
                // Labels should be in a closure to allow for translation

                NavigationItem::make()
                    ->visible(fn (): bool => auth()->user()?->can('view-logs') ?? false)
                    ->group(EnumsNavigationGroup::System)
                    ->sort(3)
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->label(fn (): string => __('common.view_logs'))
                    ->url(config('log-viewer.route_path')),
            ])
            ->plugins([
                FilamentSpatieLaravelBackupPlugin::make()
                    ->usingPage(Backups::class),
                PanelSwitcherPlugin::make(),
                ClearCachesPlugin::make(),
                LanguageSwitcherPlugin::make(),
            ])
            ->bootUsing(function (): void {
                activity()->enableLogging();
            })
            ->brandName('phpvms')
            ->brandLogo(fn (): Factory|View => view('filament.shared.brand'))
            ->brandLogoHeight('3rem')
            ->font('Geist')
            ->favicon(asset('assets/img/favicon.png'))
            ->renderHook(
                PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE,
                fn (): string => view('filament.auth.login-hero')->render(),
            )
            // UTC clock, pinned to the head of the module rail.
            ->renderHook(
                PanelsRenderHook::SIDEBAR_START,
                fn (): string => view('filament.shared.rail-clock')->render(),
            )
            // Workspace navigator: the active module's items as topbar tabs,
            // so moving inside a module never needs the rail. Hooked after the
            // logo rather than at TOPBAR_START, which would render it ahead of
            // the brand.
            ->renderHook(
                PanelsRenderHook::TOPBAR_LOGO_AFTER,
                fn (): string => view('filament.shared.workspace-nav')->render(),
            )
            // Inject vite this way - it might not exist when this is registered
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => Blade::render("@vite('resources/js/admin/app.js')"),
            )
            // Theme picker: brand colour, appearance, density — lands in the
            // topbar tools cluster right before the search box.
            ->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_BEFORE,
                fn (): string => view('filament.plugins.theme-picker')->render(),
            )
            ->breadcrumbs(false)
            ->unsavedChangesAlerts()
            ->spa(hasPrefetching: config('phpvms.use_prefetching_in_admin', false))
            ->errorNotifications()
            ->databaseNotifications()
            ->viteTheme('resources/css/filament/admin/theme.css');

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

<?php

namespace App\Providers\Filament;

use App\Enums\NavigationGroup as EnumsNavigationGroup;
use App\Filament\Pages\Backups;
use App\Filament\Plugins\ClearCachesPlugin;
use App\Filament\Plugins\LanguageSwitcherPlugin;
use App\Filament\Plugins\ModuleLinksPlugin;
use App\Filament\Plugins\SidebarCollapseTogglePlugin;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Assets\AlpineComponent;
use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Contracts\View\Factory;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Vite;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Override;
use ShuvroRoy\FilamentSpatieLaravelBackup\FilamentSpatieLaravelBackupPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->colors([
                'primary' => Color::generatePalette('#067ec1'),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
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
            ->navigationGroups([
                EnumsNavigationGroup::Operations->name,
                EnumsNavigationGroup::Config->name,
                EnumsNavigationGroup::AddOns->name,
                EnumsNavigationGroup::Developers->name,
            ])
            ->navigationItems([
                // Labels should be in a closure to allow for translation

                NavigationItem::make()
                    ->label(fn (): string => __('common.go_back_to', ['name' => config('app.name')]))
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->url('/'),

                NavigationItem::make()
                    ->visible(fn (): bool => auth()->user()->can('view-logs'))
                    ->group(EnumsNavigationGroup::Developers)
                    ->sort(3)
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->label(fn (): string => __('common.view_logs'))
                    ->url(config('log-viewer.route_path')),
            ])
            ->plugins([
                FilamentShieldPlugin::make()
                    ->navigationGroup(EnumsNavigationGroup::Config)
                    ->navigationSort(1),

                FilamentSpatieLaravelBackupPlugin::make()
                    ->usingPage(Backups::class),
                ModuleLinksPlugin::make(),
                ClearCachesPlugin::make(),
                LanguageSwitcherPlugin::make(),
                SidebarCollapseTogglePlugin::make(),
            ])
            ->bootUsing(function (): void {
                activity()->enableLogging();
            })
            ->brandName('phpvms')
            ->brandLogo(fn (): Factory|\Illuminate\Contracts\View\View => view('filament.shared.brand'))
            ->brandLogoHeight('3rem')
            ->font('Geist')
            ->favicon(asset('assets/img/favicon.png'))
            ->renderHook(
                PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE,
                fn (): string => view('filament.auth.login-hero')->render(),
            )
            ->breadcrumbs(false)
            ->unsavedChangesAlerts()
            ->spa(hasPrefetching: config('phpvms.use_prefetching_in_admin', false))
            ->errorNotifications()
            ->databaseNotifications()
            ->viteTheme('resources/css/filament/admin/theme.css');
    }

    #[Override]
    public function register(): void
    {
        parent::register();

        // Lazy-loaded admin assets. Only pulled in when a blade opts in via
        // x-load-js / x-load-css (see Filament asset docs). Keeps Leaflet and
        // the phpvms admin map bundle off pages that don't render a map.
        FilamentAsset::register([
            Js::make('phpvms-admin-maps', Vite::asset('resources/js/admin/app.js'))
                ->module()
                ->loadedOnRequest(),
            AlpineComponent::make('pirep-performance-chart', resource_path('js/dist/components/pirep-performance-chart.js')),
            AlpineComponent::make('pirep-landing-analysis', resource_path('js/dist/components/pirep-landing-analysis.js')),
            Css::make('leaflet', 'https://unpkg.com/leaflet@1.7.1/dist/leaflet.css')
                ->loadedOnRequest(),
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

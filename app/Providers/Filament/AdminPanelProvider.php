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
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentView;
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
use Illuminate\Support\Facades\Blade;
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
        // Vite hot reloading (not needed in production)
        if (!app()->isProduction()) {
            FilamentView::registerRenderHook('panels::body.end', static fn (): string => Blade::render("@vite('resources/js/app.js')"));
        }
    }
}

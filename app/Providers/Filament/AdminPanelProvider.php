<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Backups;
use App\Filament\Plugins\LanguageSwitcherPlugin;
use App\Filament\Plugins\ModuleLinksPlugin;
use App\Models\Enums\NavigationGroup as EnumsNavigationGroup;
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
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
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
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->sidebarCollapsibleOnDesktop()
            ->navigationGroups([
                \App\Models\Enums\NavigationGroup::Config->name,
                \App\Models\Enums\NavigationGroup::Operations->name,
                \App\Models\Enums\NavigationGroup::Modules->name,
                \App\Models\Enums\NavigationGroup::Developers->name,
            ])
            ->navigationItems([
                // Labels should be in a closure to allow for translation

                NavigationItem::make()
                    ->label(fn () => __('common.go_back_to', ['name' => config('app.name')]))
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->url('/'),

                NavigationItem::make()
                    ->visible(fn (): bool => auth()->user()->can('view_logs'))
                    ->group(EnumsNavigationGroup::Developers)
                    ->sort(3)
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->label(fn () => __('common.view_logs'))
                    ->url(config('log-viewer.route_path')),
            ])
            ->plugins([
                FilamentShieldPlugin::make()
                    ->navigationGroup(EnumsNavigationGroup::Config)
                    ->navigationSort(1),

                FilamentSpatieLaravelBackupPlugin::make()
                    ->usingPage(Backups::class),
                ModuleLinksPlugin::make(),
                LanguageSwitcherPlugin::make(),
            ])
            ->bootUsing(function () {
                activity()->enableLogging();
            })
            ->brandName('phpVMS')
            ->favicon(public_asset('assets/img/favicon.png'))
            ->unsavedChangesAlerts()
            ->spa(hasPrefetching: config('phpvms.use_prefetching_in_admin', false))
            ->errorNotifications()
            ->databaseNotifications()
            ->viteTheme('resources/css/filament/admin/theme.css');
    }

    public function register(): void
    {
        parent::register();
        // Vite hot reloading (not needed in production)
        if (!app()->isProduction()) {
            FilamentView::registerRenderHook('panels::body.end', static fn (): string => Blade::render("@vite('resources/js/entrypoint.js')"));
        }
    }
}

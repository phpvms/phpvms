<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Filament\Plugins\LanguageSwitcherPlugin;
use App\Http\Middleware\SetActiveLanguage;
use Filament\Enums\ThemeMode;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class SystemPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('system')
            ->path('system')
            ->colors([
                'primary' => Color::generatePalette(
                    '#067ec1',
                ),
            ])
            ->discoverPages(in: app_path('Filament/System'), for: 'App\\Filament\\System')
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
                SetActiveLanguage::class,
            ])
            ->plugins([
                LanguageSwitcherPlugin::make(),
            ])
            // The full wordmark replaces the vendor logo (hidden by the admin
            // theme); no switcher, so an install/update can't wander off.
            ->renderHook(
                PanelsRenderHook::TOPBAR_LOGO_AFTER,
                fn (): View => view('filament.system.topbar-logo'),
            )
            ->defaultThemeMode(ThemeMode::Light)
            ->brandName('phpvms')
            // No ->font(): this panel renders the same theme.css as the admin
            // one, which owns the typeface via --font-sans.
            ->brandLogo(fn (): Factory|View => view('filament.shared.brand'))
            ->brandLogoHeight('3rem')
            // A closure so the URL uses the request's host, not the one at boot (Octane).
            ->favicon(fn (): string => asset('assets/img/favicon.png'))
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->unsavedChangesAlerts()
            ->navigation(false)
            ->maxContentWidth(Width::Full)
            ->spa()
            ->breadcrumbs(false)
            ->errorNotifications();
    }
}

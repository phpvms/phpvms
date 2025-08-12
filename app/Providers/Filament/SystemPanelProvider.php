<?php

namespace App\Providers\Filament;

use App\Filament\Plugins\LanguageSwitcherPlugin;
use App\Http\Middleware\SetActiveLanguage;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
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
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                SetActiveLanguage::class,
            ])
            ->plugins([
                LanguageSwitcherPlugin::make(),
            ])
            ->brandName('phpVMS')
            ->favicon(public_asset('assets/img/favicon.png'))
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->unsavedChangesAlerts()
            ->navigation(false)
            ->spa()
            ->errorNotifications();
    }
}

<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Filament\Plugins\ClearCachesPlugin;
use App\Filament\Plugins\LanguageSwitcherPlugin;
use App\Filament\Plugins\PanelSwitcherPlugin;
use App\Support\Branding;
use Daljo25\FilamentTablerIcons\Enums\TablerIcon;
use Filament\Enums\UserMenuPosition;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider as FilamentPanelProvider;
use Filament\Support\Enums\Width;
use Filament\Tables\View\TablesIconAlias;
use Filament\View\PanelsIconAlias;
use Filament\View\PanelsRenderHook;
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

/**
 * Shared console chrome for every Filament panel — the main admin panel and
 * each module's own panel extend this and call {@see applyConsoleChrome()}
 * first, so the ops-console look (rail, topbar, hero band, theme picker,
 * UTC clock, icon set) is defined exactly once.
 *
 * Panel identity (id/path), colors, navigation groups, component discovery,
 * and any extra plugins stay in the concrete providers.
 */
abstract class BasePanelProvider extends FilamentPanelProvider
{
    protected function applyConsoleChrome(Panel $panel): Panel
    {
        return $panel
            ->login()
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
            // 76px collapsed — the console rail width, wide enough for the
            // module label the theme draws under each icon.
            ->collapsedSidebarWidth('4.75rem')
            // Rail-foot avatar, not a topbar dropdown — the console rail is
            // the one persistent chrome element, so account access lives
            // there instead of in the bar that scrolls out of memory.
            ->userMenu(position: UserMenuPosition::Sidebar)
            // Full-bleed workspace; without this, pages fall to Filament's
            // 7xl default and render max-width-centred.
            ->maxContentWidth(Width::Full)
            ->plugins([
                PanelSwitcherPlugin::make(),
                ClearCachesPlugin::make(),
                LanguageSwitcherPlugin::make(),
            ])
            ->bootUsing(function (): void {
                activity()->enableLogging();
            })
            ->brandLogo(fn (): Factory|View => view('filament.shared.brand'))
            ->brandLogoHeight('3rem')
            ->font('Geist')
            ->brandName(fn (): string => app(Branding::class)->name())
            ->favicon(fn (): string => app(Branding::class)->favicon())
            ->renderHook(
                PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE,
                fn (): string => view('filament.auth.login-hero')->render(),
            )
            // Resolves the sidebar's collapse state from localStorage before the
            // rail paints, so it does not blink out while Alpine boots. Must stay
            // ahead of the clock: it wants to run as early inside the <aside> as
            // possible.
            ->renderHook(
                PanelsRenderHook::SIDEBAR_START,
                fn (): string => view('filament.shared.sidebar-state')->render(),
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
                fn (): string => Blade::render("@vite('resources/js/apps/admin/app.js')"),
            )
            // Theme picker: brand colour, appearance, density — lands in the
            // topbar tools cluster right before the search box.
            ->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_BEFORE,
                fn (): string => view('filament.plugins.theme-picker')->render(),
            )
            // Tabler equivalents for a few ubiquitous chrome glyphs.
            ->icons([
                PanelsIconAlias::GLOBAL_SEARCH_FIELD                       => TablerIcon::Search,
                PanelsIconAlias::TOPBAR_OPEN_DATABASE_NOTIFICATIONS_BUTTON => TablerIcon::Bell,
                TablesIconAlias::ACTIONS_FILTER                            => TablerIcon::Filter,
            ])
            ->unsavedChangesAlerts()
            ->spa(hasPrefetching: config('phpvms.use_prefetching_in_admin', false))
            ->errorNotifications()
            ->databaseNotifications()
            ->viteTheme('resources/css/filament/admin/theme.css');
    }
}

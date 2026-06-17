<?php

declare(strict_types=1);

namespace App\Contracts\Modules;

use App\Enums\NavigationGroup;
use App\Filament\Plugins\ClearCachesPlugin;
use App\Filament\Plugins\LanguageSwitcherPlugin;
use App\Filament\Plugins\PanelSwitcherPlugin;
use App\Filament\Plugins\SidebarCollapseTogglePlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider as FilamentPanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
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
use Illuminate\Support\Str;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Override;
use ReflectionClass;

/**
 * Batteries-included base Filament panel provider for modules.
 *
 * A module ships its own Filament panel by extending this class and supplying
 * only {@see moduleKey()}; everything else — id, path, middleware, auth,
 * branding, theme, navigation, the panel switcher, and convention-based
 * discovery of the module's own Filament/{Resources,Pages,Widgets} — is
 * pre-configured here.
 *
 * Convention: the extending provider lives at
 *   {module-root}/Providers/Filament/XxxAdminPanelProvider.php
 * so the module root is three levels up from the provider file, and the root
 * namespace is everything before `\Providers\`. Override moduleBasePath() /
 * moduleRootNamespace() for non-standard layouts.
 *
 * The panel id equals the module key so per-module access (access:{module-key},
 * resolved in User::canAccessPanel()) gates the panel.
 *
 * Octane-safe: no mutable instance state; all registration is idempotent.
 */
abstract class PanelProvider extends FilamentPanelProvider
{
    /**
     * The module's short machine key. Used as the panel id and path segment
     * (path = `admin/{moduleKey}`) and as the access-permission suffix.
     */
    abstract protected function moduleKey(): string;

    #[Override]
    public function panel(Panel $panel): Panel
    {
        $panel = $panel
            ->id($this->moduleKey())
            ->path('admin/'.$this->moduleKey())
            ->colors($this->colors())
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
                NavigationGroup::Operations->name,
                NavigationGroup::Config->name,
                NavigationGroup::Developers->name,
            ])
            ->navigationItems([
                // Labels should be in a closure to allow for translation
                NavigationItem::make()
                    ->label(fn (): string => __('common.go_back_to', ['name' => config('app.name')]))
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->url('/'),
            ])
            ->pages([
                Dashboard::class,
            ])
            ->plugins([
                PanelSwitcherPlugin::make(),
                ClearCachesPlugin::make(),
                LanguageSwitcherPlugin::make(),
                SidebarCollapseTogglePlugin::make(),
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
                PanelsRenderHook::HEAD_END,
                fn (): string => Blade::render("@vite('resources/js/admin/app.js')"),
            )
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->unsavedChangesAlerts()
            ->spa(hasPrefetching: config('phpvms.use_prefetching_in_admin', false))
            ->breadcrumbs(false)
            ->databaseNotifications()
            ->errorNotifications();

        $this->discoverModuleComponents($panel);

        return $panel;
    }

    /**
     * The panel colour palette. Override to rebrand the module panel.
     *
     * @return array<string, array<int, string>|string>
     */
    protected function colors(): array
    {
        return [
            'primary' => Color::generatePalette('#067ec1'),
        ];
    }

    /**
     * Discover the module's own Filament components and register them on the
     * panel. Only directories that exist are registered.
     */
    protected function discoverModuleComponents(Panel $panel): void
    {
        $base = $this->moduleBasePath();
        $namespace = rtrim($this->moduleRootNamespace(), '\\');

        if ($namespace === '') {
            return;
        }

        $filamentBase = $base.'/Filament';

        $components = [
            'Resources' => 'discoverResources',
            'Pages'     => 'discoverPages',
            'Widgets'   => 'discoverWidgets',
        ];

        foreach ($components as $component => $method) {
            $dir = $filamentBase.'/'.$component;

            if (!is_dir($dir)) {
                continue;
            }

            $panel->{$method}(
                in: $dir,
                for: $namespace.'\\Filament\\'.$component,
            );
        }
    }

    /**
     * Resolve the module root directory.
     *
     * Default: 3 levels up from the provider file, which lives at
     * `{module-root}/Providers/Filament/XxxAdminPanelProvider.php`.
     */
    protected function moduleBasePath(): string
    {
        return dirname((string) new ReflectionClass(static::class)->getFileName(), 3);
    }

    /**
     * Resolve the root PHP namespace of this module.
     *
     * Default: everything before `\Providers\` in the provider's FQCN, e.g.
     * `Modules\Sample\Providers\Filament\SampleAdminPanelProvider` → `Modules\Sample`.
     * Returns '' when it can't be inferred; discovery is then skipped.
     */
    protected function moduleRootNamespace(): string
    {
        if (!str_contains(static::class, '\\Providers\\')) {
            return '';
        }

        return Str::beforeLast(static::class, '\\Providers\\');
    }
}

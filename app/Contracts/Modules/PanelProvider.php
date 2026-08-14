<?php

declare(strict_types=1);

namespace App\Contracts\Modules;

use App\Enums\NavigationGroup as EnumsNavigationGroup;
use App\Filament\Pages\AddonSettings;
use App\Providers\Filament\BasePanelProvider;
use App\Support\Branding;
use Daljo25\FilamentTablerIcons\Enums\TablerIcon;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Illuminate\Support\Str;
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
abstract class PanelProvider extends BasePanelProvider
{
    /**
     * The module's short machine key. Used as the panel id and path segment
     * (path = `admin/{moduleKey}`) and as the access-permission suffix.
     */
    abstract protected function moduleKey(): string;

    #[Override]
    public function panel(Panel $panel): Panel
    {
        $panel = $this->applyConsoleChrome($panel)
            ->id($this->moduleKey())
            ->path('admin/'.$this->moduleKey())
            ->colors(fn (): array => $this->colors())
            /* Icon-bearing group objects, mirroring AdminPanelProvider —
             * groups registered as bare name strings have no icon, so the
             * collapsed rail can't render its icon-only trigger and falls
             * back to stacking every item inline. Keys are enum case names so
             * NavigationManager matches on the key rather than falling back to
             * the translated label, which only lines up under English. */
            ->navigationGroups([
                EnumsNavigationGroup::Operations->name => NavigationGroup::make()
                    ->label(fn (): string => EnumsNavigationGroup::Operations->getLabel())
                    ->icon(TablerIcon::Plane),
                EnumsNavigationGroup::Config->name => NavigationGroup::make()
                    ->label(fn (): string => EnumsNavigationGroup::Config->getLabel())
                    ->icon(TablerIcon::Settings),
                EnumsNavigationGroup::System->name => NavigationGroup::make()
                    ->label(fn (): string => EnumsNavigationGroup::System->getLabel())
                    ->icon(TablerIcon::Terminal),
            ])
            ->pages([
                Dashboard::class,
                // Shared settings editor; auto-hidden unless this addon has
                // registered settings (see AddonSettings::canAccess()).
                AddonSettings::class,
            ]);

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
            'primary' => app(Branding::class)->brandPalette(),
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

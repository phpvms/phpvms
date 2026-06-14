<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\NavigationGroup;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Override;
use UnitEnum;

/**
 * RouteForge admin tool — thin Filament page wrapper around a Preact SPA.
 *
 * The page is intentionally O(1) at mount: NO Eloquent queries, NO data
 * envelope assembly, NO `window.*` globals. The Blade view renders a single
 * mount element with the `/admin/route-forge/api/boot` URL on a
 * `data-boot-url` attribute; the SPA fetches that URL once and hydrates its
 * in-memory store from the response.
 *
 * See `App\Http\Controllers\Admin\RouteForgeController::boot` for the boot
 * envelope contract, and `openspec/changes/routeforge-page-boot-via-api`
 * for the documented "Filament page hosting an SPA" convention this page
 * pioneers in the codebase.
 */
class RouteForge extends Page
{
    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Operations;

    protected static ?int $navigationSort = 3;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected string $view = 'filament.pages.route-forge';

    /**
     * Absolute URL of the boot endpoint, rendered into `#routeforge-root`'s
     * `data-boot-url` attribute by the Blade view. Resolved in `mount()` from
     * the named route so route prefix changes flow through automatically.
     */
    public string $bootUrl = '';

    #[Override]
    public static function canAccess(): bool
    {
        // Reuses the existing flight-edit permission rather than introducing
        // dedicated routeforge.* permissions. RouteForge bulk-creates flights,
        // so the same gate that protects flight creation/editing applies here.
        return auth()->user()?->can('edit:flight') ?? false;
    }

    #[Override]
    public static function getNavigationLabel(): string
    {
        return __('filament.routeforge.navigation_label');
    }

    #[Override]
    public function getTitle(): string
    {
        return __('filament.routeforge.page_title');
    }

    #[Override]
    public function getSubheading(): ?string
    {
        return __('filament.routeforge.page_subtitle');
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        // Single line of mount-time work. NO Eloquent queries, NO config
        // payload assembly — the SPA fetches /boot itself after render.
        $this->bootUrl = route('admin.routeforge.api.boot');
    }
}

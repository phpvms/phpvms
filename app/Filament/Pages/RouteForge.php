<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\NavigationGroup;
use BackedEnum;
use Filafly\Icons\Phosphor\Enums\Phosphor;
use Filament\Pages\Page;
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
    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Planning;

    protected static ?int $navigationSort = 2;

    protected static string|BackedEnum|null $navigationIcon = Phosphor::StackLight;

    protected string $view = 'filament.pages.route-forge';

    /**
     * Absolute URL of the boot endpoint, rendered into `#routeforge-root`'s
     * `data-boot-url` attribute by the Blade view. Resolved in `mount()` from
     * the named route so route prefix changes flow through automatically.
     */
    public string $bootUrl = '';

    /**
     * Whitelisted deep-link prefill, rendered as JSON into `#routeforge-root`'s
     * `data-prefill` attribute and folded into the SPA store by
     * `resources/js/apps/admin/routeforge/lib/prefill.ts` before first render.
     * Empty when the page was opened from the nav rather than a bundle page.
     *
     * @var array{topology?: string, bundle_id?: int, bundle_name?: string, fresh?: bool}
     */
    public array $prefill = [];

    /**
     * Topologies the SPA understands (`state/types.ts` `Topology`). An unknown
     * value in the URL is dropped rather than handed to the client.
     *
     * @var list<string>
     */
    private const array TOPOLOGIES = ['hub_spokes', 'spokes_hub', 'hub_and_spokes', 'mesh', 'tour'];

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

        // Still NO Eloquent queries and NO config payload assembly — the SPA
        // fetches /boot itself after render. The prefill below is pure query
        // string reading; the bundle it names is resolved by the SPA against
        // the existing /bundles endpoint.
        $this->bootUrl = route('admin.routeforge.api.boot');
        $this->prefill = $this->readPrefill();
    }

    /**
     * Read the bundle-page deep link:
     * `?topology=tour&bundle=12&bundle_name=Pacific+Tour&fresh=1`.
     *
     * `bundle_name` seeds the read-only bundle summary so the SPA can render
     * it without a by-id lookup the `/bundles` endpoint does not offer; it is
     * display text only, and the SPA re-resolves the bundle from the server.
     * `fresh` suppresses draft resume so a stale draft cannot swallow the link.
     *
     * @return array{topology?: string, bundle_id?: int, bundle_name?: string, fresh?: bool}
     */
    private function readPrefill(): array
    {
        $request = request();
        $prefill = [];

        $topology = $request->query('topology');
        if (is_string($topology) && in_array($topology, self::TOPOLOGIES, true)) {
            $prefill['topology'] = $topology;
        }

        $bundle = $request->query('bundle');
        if (is_numeric($bundle) && (int) $bundle > 0) {
            $prefill['bundle_id'] = (int) $bundle;

            $name = $request->query('bundle_name');
            if (is_string($name) && $name !== '') {
                $prefill['bundle_name'] = $name;
            }
        }

        if ($prefill !== [] && $request->boolean('fresh')) {
            $prefill['fresh'] = true;
        }

        return $prefill;
    }
}

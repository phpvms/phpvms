<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\NavigationGroup;
use App\Models\Airline;
use App\Models\FlightBundle;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\App;

class RouteForge extends Page
{
    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Operations;

    protected static ?int $navigationSort = 3;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected string $view = 'filament.pages.route-forge';

    /**
     * Config payload exposed to the TypeScript bundle via `window.routeforgeConfig`.
     *
     * Populated in mount(); read by the Blade view.
     *
     * @var array<string, mixed>
     */
    public array $config = [];

    #[\Override]
    public static function canAccess(): bool
    {
        // Reuses the existing flight-create permission rather than introducing
        // dedicated routeforge.* permissions. RouteForge bulk-creates flights,
        // so the same gate that protects single-flight creation applies here.
        return auth()->user()?->can('create:flight') ?? false;
    }

    #[\Override]
    public static function getNavigationLabel(): string
    {
        return __('filament.routeforge.navigation_label');
    }

    #[\Override]
    public function getTitle(): string
    {
        return __('filament.routeforge.page_title');
    }

    #[\Override]
    public function getSubheading(): ?string
    {
        return __('filament.routeforge.page_subtitle');
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->config = $this->buildConfig();
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildConfig(): array
    {
        return [
            'csrf_token'   => csrf_token(),
            'locale'       => App::getLocale(),
            'user'         => $this->buildUserPayload(),
            'airlines'     => $this->buildAirlinesPayload(),
            'bundles'      => $this->buildBundlesPayload(),
            'routes'       => $this->buildRoutesPayload(),
            'config'       => config('phpvms.routeforge', []),
            'translations' => $this->buildTranslationsPayload(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildUserPayload(): array
    {
        $user = auth()->user();

        if ($user === null) {
            return [];
        }

        return [
            'id'         => $user->id,
            'name'       => $user->name ?? null,
            'can_commit' => $user->can('create:flight'),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function buildAirlinesPayload(): array
    {
        return Airline::query()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'icao', 'iata'])
            ->map(fn (Airline $airline): array => [
                'id'   => $airline->id,
                'name' => $airline->name,
                'icao' => $airline->icao,
                'iata' => $airline->iata,
            ])
            ->all();
    }

    /**
     * Existing FlightBundles for the in-page picker. Soft-deleted rows are
     * excluded automatically by the SoftDeletes scope. The picker filters
     * in-memory client-side so we ship the full set at mount time — fine at
     * typical VA scale (dozens to low hundreds of bundles).
     *
     * @return array<int, array<string, mixed>>
     */
    protected function buildBundlesPayload(): array
    {
        return FlightBundle::query()
            ->orderBy('name')
            ->get(['id', 'name', 'description', 'enabled', 'start_date', 'end_date'])
            ->map(fn (FlightBundle $bundle): array => [
                'id'          => $bundle->id,
                'name'        => $bundle->name,
                'description' => $bundle->description,
                'enabled'     => $bundle->enabled,
                'start_date'  => $bundle->start_date?->format('Y-m-d'),
                'end_date'    => $bundle->end_date?->format('Y-m-d'),
            ])
            ->all();
    }

    /**
     * Hardcoded API URLs. Section 5 registers them as named routes; once
     * those land we can swap to `route('admin.routeforge.api.*')` calls.
     *
     * @return array<string, string>
     */
    protected function buildRoutesPayload(): array
    {
        $base = url('/admin/route-forge/api');

        return [
            'preview_airports' => $base.'/preview-airports',
            'subfleets'        => $base.'/subfleets',
            'airline_stats'    => $base.'/airline-stats',
            'check_duplicates' => $base.'/check-duplicates',
            'lint'             => $base.'/lint',
            'commit'           => $base.'/commit',
        ];
    }

    /**
     * Collect all `filament.routeforge.*` translation keys for the TS bundle.
     *
     * Returns a nested array; the TS `t()` helper (task 8.3) walks dot paths.
     *
     * @return array<string, mixed>
     */
    protected function buildTranslationsPayload(): array
    {
        $translations = trans('filament.routeforge');

        return is_array($translations) ? $translations : [];
    }
}

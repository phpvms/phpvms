<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\NavigationGroup;
use App\Filament\Resources\FlightBundles\FlightBundleResource;
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
     * Named API URLs exposed to the TypeScript bundle. Resolved via Laravel's
     * route() helper so prefix or group changes flow through automatically.
     *
     * The bundle-edit URL is a template — `:id` gets substituted client-side
     * in CommitSuccessRedirect once the commit response carries bundle_id.
     * We derive the path from FlightBundleResource::getUrl() rather than
     * hardcoding `/admin/flight-bundles/...` because the resource's `$slug`
     * is `flights` (not `flight-bundles`) — hardcoding would silently
     * redirect to a 404. The sentinel `__RF_BUNDLE_ID__` is alphanumeric so
     * Laravel's URL generator leaves it untouched, after which we swap it
     * for the `:id` placeholder the TS template expects.
     *
     * @return array<string, string>
     */
    protected function buildRoutesPayload(): array
    {
        $sentinel = '__RF_BUNDLE_ID__';
        $bundleEditTemplate = str_replace(
            $sentinel,
            ':id',
            FlightBundleResource::getUrl('edit', ['record' => $sentinel]),
        );

        return [
            'preview_airports'     => route('admin.routeforge.api.preview-airports'),
            'subfleets'            => route('admin.routeforge.api.subfleets'),
            'airline_stats'        => route('admin.routeforge.api.airline-stats'),
            'check_duplicates'     => route('admin.routeforge.api.check-duplicates'),
            'lint'                 => route('admin.routeforge.api.lint'),
            'commit'               => route('admin.routeforge.api.commit'),
            'bundle_edit_template' => $bundleEditTemplate,
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

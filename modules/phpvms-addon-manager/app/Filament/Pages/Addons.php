<?php

declare(strict_types=1);

namespace Modules\AddonManager\Filament\Pages;

use App\Enums\NavigationGroup;
use App\Features\Addons\AddonDeleteOutcome;
use App\Features\Addons\AddonRegistry;
use App\Features\Addons\Services\AddonDiscoveryService;
use App\Features\Addons\Sources\ZipSource;
use App\Features\Addons\Support\AddonIcon;
use App\Features\Addons\Support\ManifestParser;
use App\Filament\Concerns\AuthorizesAccess;
use App\Models\Addon;
use BackedEnum;
use Filafly\Icons\Phosphor\Enums\Phosphor;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Size;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Modules\AddonManager\Jobs\InstallAddonJob;
use Modules\AddonManager\Services\CompatibilityEvaluator;
use Modules\AddonManager\Services\RegistryClient;
use Modules\AddonManager\Support\InstallProgress;
use Override;
use Throwable;
use UnitEnum;

/**
 * Addon manager: browse the registry, install/update from it, and manage
 * installed addons as a card grid (a Filament table over custom data) with a
 * slide-over detail. Discovered into the main admin panel by AdminPanelProvider's
 * module-page discovery.
 *
 * The rows are assembled in-memory each render by merging the cached registry
 * catalog (RegistryClient) with the installed addon rows; the banner tab,
 * search and filters are applied to them inside the table's records() closure.
 */
class Addons extends Page implements HasTable
{
    use AuthorizesAccess;
    use InteractsWithTable;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::AddOns;

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Phosphor::PuzzlePieceLight;

    /**
     * The tab a bare /admin/addons lands on. It has to agree with the first
     * banner tab, or arriving at the page highlights a tab you did not pick.
     * Installed leads because "why is that add-on not running" is the question
     * people come here with; browsing the registry is a deliberate second step.
     */
    private const string DEFAULT_TAB = 'installed';

    /**
     * Which population is listed. Bound to `?tab=` so a tab can be linked
     * straight to; still a plain property, so `set()` works.
     */
    #[Url(as: 'tab')]
    public string $activeTab = self::DEFAULT_TAB;

    /**
     * Per-request memo for the assembled listing — allEntries() runs a catalog
     * read + a DB read and is consulted several times per render (records, counts,
     * categories, subheading). Fresh each request, and dropped after any state
     * change (see refreshEntries()).
     *
     * @var Collection<int, array<string, mixed>>|null
     */
    private ?Collection $entriesMemo = null;

    /**
     * The addons area's permission is historically named `modules` (see the v7
     * import migration, User::canAccessPanel, and ModuleLinksPlugin), so pin the
     * key rather than deriving `addons` from the class name.
     */
    public static function getPermissionKey(): string
    {
        return 'modules';
    }

    /**
     * One nav entry under the Add-ons group; All / Installed / Updates / Not
     * installed are the page's own banner tabs (see content()).
     */
    #[Override]
    public static function getNavigationLabel(): string
    {
        return __('addon-manager::addons.view');
    }

    /** The update count is the badge, so it reads as a warning. */
    #[Override]
    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'warning';
    }

    /** A zero count is not news; leave the tab unbadged. */
    private function badge(int $count): ?string
    {
        return $count > 0 ? (string) $count : null;
    }

    #[Override]
    public static function getNavigationBadge(): ?string
    {
        $count = app(static::class)->updateCount();

        return $count > 0 ? (string) $count : null;
    }

    // ── Page chrome ────────────────────────────────────────────────────────

    #[Override]
    public function getHeading(): string
    {
        return __('addon-manager::addons.heading');
    }

    /**
     * Three counts instead of a sentence, in the band header's metrics row —
     * the same treatment the PIREP list uses.
     */
    #[Override]
    public function getSubheading(): string|Htmlable|null
    {
        $rows = $this->allEntries();

        return view('addon-manager::filament.addons.head-metrics', [
            'listed'   => $this->tabCounts()[$this->activeTab] ?? $rows->count(),
            'updates'  => $rows->where('update_available', true)->count(),
            'disabled' => $rows->where('installed', true)->where('enabled', false)->count(),
        ]);
    }

    /**
     * @return array<int, Action>
     */
    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            $this->uploadZipAction(),
            Action::make('checkUpdates')
                ->label(__('addon-manager::addons.check_updates'))
                ->icon(Phosphor::ArrowsClockwiseLight)
                ->color('gray')
                ->action(fn () => $this->refreshCatalog()),
        ];
    }

    /**
     * Banner tabs, then the stale-catalog notice, then the card grid.
     */
    #[Override]
    public function content(Schema $schema): Schema
    {
        return $schema->components([
            // First in the content, so the theme lifts it onto the banner's
            // bottom edge as folder tabs. The tabs only switch `activeTab`;
            // the table below reads it.
            Tabs::make()
                ->key('addonTabs')
                ->livewireProperty('activeTab')
                ->contained(false)
                ->tabs([
                    'all' => Tab::make(__('addon-manager::addons.all_tab'))
                        ->icon(Phosphor::SquaresFourLight)
                        ->badge(fn (): ?string => $this->badge($this->tabCounts()['all'])),
                    'installed' => Tab::make(__('addon-manager::addons.installed_tab'))
                        ->icon(Phosphor::PuzzlePieceLight)
                        ->badge(fn (): ?string => $this->badge($this->tabCounts()['installed'])),
                    'updates' => Tab::make(__('addon-manager::addons.updates'))
                        ->icon(Phosphor::DownloadSimpleLight)
                        ->badge(fn (): ?string => $this->badge($this->tabCounts()['updates']))
                        ->badgeColor('warning'),
                    'available' => Tab::make(__('addon-manager::addons.not_installed_tab'))
                        ->icon(Phosphor::MagnifyingGlassLight)
                        ->badge(fn (): ?string => $this->badge($this->tabCounts()['available'])),
                ]),

            // The search box, category menu and toggles drive the table's own
            // search/filter state; the native controls are hidden.
            View::make('addon-manager::filament.addons.filter-bar'),

            View::make('addon-manager::filament.addons.notice')
                ->visible(fn (): bool => $this->catalogState()['stale'] || filled($this->catalogState()['error'])),

            EmbeddedTable::make(),
        ]);
    }

    // ── Data assembly ──────────────────────────────────────────────────────

    /**
     * Registry catalog state (entries + freshness), served stale on failure.
     *
     * @return array{entries: array<string, array<string, mixed>>, synced_at: ?string, stale: bool, error: ?string}
     */
    public function catalogState(): array
    {
        return app(RegistryClient::class)->catalog();
    }

    /**
     * Every catalog entry merged with its installed row (compatibility + update
     * state resolved), plus installed addons absent from the catalog (local/zip
     * uploads) so they still surface on the Installed tab.
     *
     * @return Collection<int, array<string, mixed>>
     */
    /**
     * Scan the disk for addons with no DB row (FTP upload / files left behind)
     * once per page load. Kept OUT of allEntries() — which runs on every Livewire
     * update — so selecting a row no longer triggers a filesystem scan.
     */
    public function mount(): void
    {
        app(AddonDiscoveryService::class)->discoverNewAddons();
    }

    public function allEntries(): Collection
    {
        if ($this->entriesMemo instanceof Collection) {
            return $this->entriesMemo;
        }

        // Key installed rows case-insensitively: the installed `registry_id` comes
        // from the downloaded addon's own manifest and the catalog key from the
        // API — registry ids are conventionally lowercase, so normalise both sides
        // to avoid a case drift splitting one addon into two entries.
        $installed = app(AddonRegistry::class)->all()
            ->keyBy(fn (Addon $addon): string => Str::lower($addon->registry_id ?: $addon->getName()));

        $evaluator = app(CompatibilityEvaluator::class);
        $catalog = $this->catalogState()['entries'];

        $entries = collect($catalog)->map(function (array $entry) use ($installed, $evaluator): array {
            $addon = $installed->get(Str::lower((string) $entry['registry_id']));
            $compat = $evaluator->evaluate($entry);

            return $this->row($entry, $addon, $compat['compatible'], $compat['reason'], inCatalog: true);
        });

        // Installed addons the catalog doesn't list (local uploads, bundled
        // modules): show them as installed+compatible with no upgrade path.
        $catalogKeys = $entries->pluck('id')->map(fn (string $id): string => Str::lower($id))->all();
        $local = $installed
            ->reject(fn (Addon $addon, string $key): bool => in_array($key, $catalogKeys, true))
            ->map(fn (Addon $addon): array => $this->row([
                'registry_id'    => $addon->registry_id ?: $addon->getName(),
                'name'           => $addon->getName(),
                'description'    => '',
                'category'       => '',
                'license'        => '',
                'publisher'      => '',
                'version'        => (string) $addon->version,
                'icon'           => null,
                'installs_total' => 0,
            ], $addon, true, null, inCatalog: false))
            ->values();

        return $this->entriesMemo = $entries->values()->concat($local);
    }

    /**
     * The icon URL an installed addon declares in its manifest, or null.
     */
    private function manifestIcon(?Addon $addon): ?string
    {
        if (!$addon instanceof Addon) {
            return null;
        }

        try {
            $icon = new ManifestParser()->parse($addon->getPath())?->icon;
        } catch (Throwable) {
            return null;
        }

        return AddonIcon::url($addon->getName(), $addon->getPath(), $icon);
    }

    /**
     * Shape one merged listing row.
     *
     * @param  array<string, mixed> $entry
     * @return array<string, mixed>
     */
    private function row(array $entry, ?Addon $addon, bool $compatible, ?string $incompatibleReason, bool $inCatalog): array
    {
        $latest = (string) ($entry['version'] ?? '');
        $installedVersion = $addon?->version;
        $updateAvailable = $addon instanceof Addon && $latest !== '' && app(CompatibilityEvaluator::class)->isNewer($latest, (string) $installedVersion);

        return [
            'id'          => (string) $entry['registry_id'],
            'in_catalog'  => $inCatalog,
            'name'        => (string) $entry['name'],
            'description' => (string) ($entry['description'] ?? ''),
            'category'    => (string) ($entry['category'] ?? ''),
            'license'     => (string) ($entry['license'] ?? ''),
            'publisher'   => (string) ($entry['publisher'] ?? ''),
            // Published by the phpVMS project rather than a third party. Earns a
            // star; never affects whether it can be installed.
            'official' => (bool) ($entry['official'] ?? false),
            // Registry-supplied URLs are rendered as hrefs; drop any non-http(s)
            // scheme (e.g. javascript:) so a hostile registry can't inject one.
            'repository_url' => $this->safeUrl((string) ($entry['repository_url'] ?? '')),
            // Where the add-on lives for a human: its own site if it has one,
            // otherwise the repository. Both are optional and the row is hidden
            // when neither is supplied.
            'product_url' => $this->safeUrl((string) ($entry['product_url'] ?? '')),
            // Not yet emitted by registry.phpvms.net — read defensively so the
            // row appears the moment the registry starts sending it.
            'changelog_url' => $this->safeUrl((string) ($entry['changelog_url'] ?? '')),
            // The registry's listing icon, else the installed addon's manifest icon.
            'icon'                => $this->safeUrl((string) ($entry['icon'] ?? '')) ?: $this->manifestIcon($addon),
            'monogram'            => $this->monogram((string) $entry['name']),
            'tint'                => $this->tint((string) ($entry['category'] ?? ''), (string) $entry['registry_id']),
            'installs'            => (int) ($entry['installs_total'] ?? 0),
            'min_php'             => (string) ($entry['min_php'] ?? ''),
            'min_phpvms'          => (string) ($entry['min_phpvms'] ?? ''),
            'latest_version'      => $latest,
            'installed'           => $addon instanceof Addon,
            'installed_key'       => $addon?->getName(),
            'installed_version'   => $installedVersion !== null ? (string) $installedVersion : null,
            'enabled'             => $addon?->isEnabled() ?? false,
            'bundled'             => $addon?->isBundled() ?? false,
            'update_available'    => $updateAvailable,
            'compatible'          => $compatible,
            'incompatible_reason' => $incompatibleReason,
        ];
    }

    /**
     * The monogram plate's colour, as one of the theme's shared category tints.
     *
     * Category first, so add-ons that do the same kind of job look related. An
     * add-on with no category — every locally installed one, since the category
     * only comes from the registry — falls back to a hue derived from its id, so
     * it still gets a colour and the same one everywhere it appears.
     *
     * This is the one place colour touches a data value; it marks what an add-on
     * IS, which never changes, not how it is doing. State stays with the chips.
     */
    private function tint(string $category, string $registryId): string
    {
        $byCategory = [
            'operations'   => 'blue',
            'dispatch'     => 'blue',
            'acars'        => 'blue',
            'pilots'       => 'teal',
            'awards'       => 'teal',
            'finance'      => 'amber',
            'integration'  => 'amber',
            'integrations' => 'amber',
            'system'       => 'violet',
            'reporting'    => 'rose',
        ];

        $hues = ['blue', 'teal', 'violet', 'rose', 'amber'];

        return $byCategory[Str::lower($category)] ?? $hues[crc32($registryId) % count($hues)];
    }

    /**
     * The active tab's rows, narrowed by the search box and the filters, sorted
     * A–Z by name (case-insensitive) and keyed by add-on id.
     *
     * Official and incompatible add-ons carry no sort weight: a plain
     * alphabetical shelf is predictable, and the badges already say the rest.
     *
     * @param  array<string, mixed>                     $filters the table's filter state
     * @return Collection<string, array<string, mixed>>
     */
    public function listing(?string $search = null, array $filters = []): Collection
    {
        $rows = $this->allEntries();

        $rows = match ($this->activeTab) {
            'installed' => $rows->where('installed', true),
            'updates'   => $rows->where('update_available', true),
            'available' => $rows->where('in_catalog', true)->where('installed', false),
            default     => $rows,
        };

        $category = $filters['category']['value'] ?? null;
        if (filled($category)) {
            $rows = $rows->where('category', $category);
        }

        if ($filters['official']['isActive'] ?? false) {
            $rows = $rows->where('official', true);
        }

        // "Untested" is an add-on this install cannot run and has not got.
        if ($filters['hide_untested']['isActive'] ?? false) {
            $rows = $rows->reject(fn (array $row): bool => !$row['compatible'] && !$row['installed']);
        }

        if (filled($search)) {
            $needle = Str::lower($search);
            $rows = $rows->filter(fn (array $row): bool => str_contains(Str::lower($row['name']), $needle)
                || str_contains(Str::lower($row['description']), $needle)
                || str_contains(Str::lower($row['id']), $needle)
                || str_contains(Str::lower($row['publisher']), $needle));
        }

        return $rows
            ->sortBy(fn (array $row): string => Str::lower($row['name']), SORT_NATURAL)
            ->keyBy('id');
    }

    /**
     * Distinct catalog categories for the filter select.
     *
     * @return array<int, string>
     */
    public function categories(): array
    {
        return $this->allEntries()
            ->pluck('category')
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * Switching tab swaps the whole population, so page 3 of the old one is an
     * empty grid in the new one. Search and filters are left as typed.
     */
    public function updatedActiveTab(): void
    {
        $this->resetPage();
    }

    /**
     * @return array{all: int, installed: int, updates: int, available: int}
     */
    public function tabCounts(): array
    {
        $all = $this->allEntries();

        return [
            'all'       => $all->count(),
            'installed' => $all->where('installed', true)->count(),
            'updates'   => $all->where('update_available', true)->count(),
            'available' => $all->where('in_catalog', true)->where('installed', false)->count(),
        ];
    }

    // ── Table ─────────────────────────────────────────────────────────────

    /**
     * Cards: one Stack per add-on in a two/three column content grid. Search,
     * filters and paging are applied by hand in records() (custom data), and
     * clicking a card opens the slide-over peek.
     */
    public function table(Table $table): Table
    {
        return $table
            ->records(fn (?string $search, array $filters, int $page, int $recordsPerPage): LengthAwarePaginator => $this->paginateRows($search, $filters, $page, $recordsPerPage))
            ->columns([
                // A layout component is what makes the table render as cards.
                Stack::make([
                    ViewColumn::make('card')
                        ->view('addon-manager::filament.addons.card')
                        ->state(fn (array $record): array => $this->cardData($record)),
                ]),
            ])
            ->contentGrid(['md' => 2, 'xl' => 3])
            ->paginationPageOptions([12, 24, 48])
            ->defaultPaginationPageOption(12)
            ->filters([
                SelectFilter::make('category')
                    ->label(__('addon-manager::addons.category'))
                    ->placeholder(__('addon-manager::addons.all_categories'))
                    ->options(fn (): array => array_combine($this->categories(), $this->categories())),
                Filter::make('official')
                    ->label(__('addon-manager::addons.official_only'))
                    ->toggle(),
                Filter::make('hide_untested')
                    ->label(__('addon-manager::addons.hide_untested'))
                    ->toggle(),
            ], layout: FiltersLayout::Hidden)
            ->deferFilters(false)
            ->extraAttributes(['class' => 'addon-cards'])
            ->recordUrl(null)
            ->recordAction('peek')
            ->recordActions([
                $this->installRecordAction(),
                $this->toggleRecordAction(enable: true, inMenu: false),
                $this->toggleRecordAction(enable: false, inMenu: false),
                ActionGroup::make([
                    $this->peekRecordAction(),
                    $this->toggleRecordAction(enable: true, inMenu: true),
                    $this->toggleRecordAction(enable: false, inMenu: true),
                    $this->deleteRecordAction(),
                    $this->repositoryRecordAction(),
                ])->icon(Phosphor::DotsThreeLight)->color('gray'),
            ])
            ->emptyStateIcon(Phosphor::PuzzlePieceLight)
            ->emptyStateHeading(__('addon-manager::addons.no_results_title'))
            ->emptyStateDescription(__('addon-manager::addons.no_results_hint'));
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function paginateRows(?string $search, array $filters, int $page, int $recordsPerPage): LengthAwarePaginator
    {
        $rows = $this->listing($search, $filters);

        return new LengthAwarePaginator(
            $rows->forPage($page, $recordsPerPage),
            $rows->count(),
            $recordsPerPage,
            $page,
        );
    }

    /**
     * What the card view needs beyond the row itself.
     *
     * @param  array<string, mixed>                                                                                                                               $row
     * @return array{badges: array<int, array{label: string, color: string}>, installs: ?string, progress: array{status: string, pct: int, message: string}|null}
     */
    private function cardData(array $row): array
    {
        return [
            'badges'   => $this->badgeData($row),
            'installs' => $row['installs'] > 0 ? Number::abbreviate($row['installs']) : null,
            'progress' => InstallProgress::isActive($row['id']) ? InstallProgress::get($row['id']) : null,
        ];
    }

    /**
     * @param  array<string, mixed>                            $row
     * @return array<int, array{label: string, color: string}>
     */
    private function badgeData(array $row): array
    {
        return array_map(
            fn (string $label): array => ['label' => $label, 'color' => $this->badgeColor($label)],
            $this->badgesFor($row),
        );
    }

    /**
     * Status badges for a card, in reading order.
     *
     * @param  array<string, mixed> $row
     * @return array<int, string>
     */
    private function badgesFor(array $row): array
    {
        return array_values(array_filter([
            $row['update_available'] ? __('addon-manager::addons.badge_update') : null,
            $row['installed'] && $row['enabled'] ? __('addon-manager::addons.state_enabled') : null,
            $row['installed'] && !$row['enabled'] ? __('addon-manager::addons.state_disabled') : null,
            !$row['compatible'] ? __('addon-manager::addons.untested') : null,
            $row['bundled'] ? __('addon-manager::addons.bundled_short') : null,
        ]));
    }

    private function badgeColor(string $label): string
    {
        return match ($label) {
            __('addon-manager::addons.badge_update')  => 'warning',
            __('addon-manager::addons.state_enabled') => 'success',
            __('addon-manager::addons.untested')      => 'danger',
            default                                   => 'gray',
        };
    }

    /**
     * Count installed addons with a newer catalog version — the nav badge value.
     *
     * Deliberately does NOT go through allEntries(): the badge renders on EVERY
     * admin page, and allEntries() runs a disk scan (discoverNewAddons) it does
     * not need. This is a cached-catalog read + one addons query.
     */
    public function updateCount(): int
    {
        // Cache-only: the badge renders on every admin page and must never
        // trigger a registry fetch on the request thread.
        $catalog = app(RegistryClient::class)->cachedCatalog()['entries'];

        if ($catalog === []) {
            return 0;
        }

        $lookup = collect($catalog)->keyBy(fn (array $e): string => Str::lower((string) $e['registry_id']));

        return app(AddonRegistry::class)->all()
            ->filter(function (Addon $addon) use ($lookup): bool {
                $entry = $lookup->get(Str::lower($addon->registry_id ?: $addon->getName()));
                $latest = (string) ($entry['version'] ?? '');

                return $entry !== null && $latest !== '' && app(CompatibilityEvaluator::class)->isNewer($latest, (string) $addon->version);
            })
            ->count();
    }

    // ── Filter bar ────────────────────────────────────────────────────────

    /**
     * Distinct categories with how many add-ons carry each, for the bar's menu.
     *
     * @return array<string, int>
     */
    public function categoryCounts(): array
    {
        return $this->allEntries()
            ->pluck('category')
            ->filter()
            ->countBy()
            ->sortKeys()
            ->all();
    }

    /** `null` clears the category filter. */
    public function setCategoryFilter(?string $category): void
    {
        $this->tableFilters ??= [];
        $this->tableFilters['category']['value'] = in_array($category, $this->categories(), true) ? $category : null;
        $this->updatedTableFilters();
    }

    /** Flip one of the bar's pill toggles (`official` or `hide_untested`). */
    public function toggleFilterFlag(string $flag): void
    {
        if (!in_array($flag, ['official', 'hide_untested'], true)) {
            return;
        }

        $this->tableFilters ??= [];
        $this->tableFilters[$flag]['isActive'] = !($this->tableFilters[$flag]['isActive'] ?? false);
        $this->updatedTableFilters();
    }

    // ── Row interactions ──────────────────────────────────────────────────

    public function refreshCatalog(): void
    {
        $result = app(RegistryClient::class)->refresh();

        if ($result['error'] !== null || $result['stale']) {
            Notification::make()
                ->title(__('addon-manager::addons.registry_unreachable'))
                ->body(__('addon-manager::addons.showing_cached_catalog'))
                ->warning()
                ->send();

            return;
        }

        Notification::make()->title(__('addon-manager::addons.catalog_refreshed'))->success()->send();
    }

    /**
     * Custom-data tables do not refresh after an action, and allEntries() is
     * memoised for the request — drop both so the cards show the new state.
     */
    private function refreshEntries(): void
    {
        $this->entriesMemo = null;
        $this->flushCachedTableRecords();
    }

    // ── Actions ───────────────────────────────────────────────────────────

    /** @param array<string, mixed> $row */
    private function canInstall(array $row): bool
    {
        return !$row['installed'] || $row['update_available'];
    }

    /** Enable/disable and delete are for installed add-ons that are not bundled. */
    private function canManage(array $row): bool
    {
        return $row['installed'] && !$row['bundled'];
    }

    /** @param array<string, mixed> $row */
    private function installLabel(array $row): string
    {
        return $row['update_available']
            ? __('addon-manager::addons.update_to', ['version' => $row['latest_version']])
            : __('addon-manager::addons.install');
    }

    /**
     * Why install is unavailable for a row, or null when it is available.
     *
     * @param array<string, mixed> $row
     */
    private function installBlockedReason(array $row): ?string
    {
        if (!$row['compatible']) {
            return $row['incompatible_reason'];
        }

        return ($row['latest_version'] ?? '') === '' ? __('addon-manager::addons.no_installable_version') : null;
    }

    /**
     * Install / update the clicked add-on. The modal shows publisher,
     * requirements with the compatibility result, package size and a
     * verification note above a migrations toggle. Disabled (with the reason as
     * tooltip) when incompatible or when the catalog carries no installable
     * version.
     */
    private function installRecordAction(): Action
    {
        return Action::make('install')
            ->label(fn (array $record): string => $this->installLabel($record))
            ->icon(fn (array $record): Phosphor => $record['update_available'] ? Phosphor::ArrowsClockwiseLight : Phosphor::DownloadSimpleLight)
            ->button()
            ->size(Size::Small)
            ->modalHeading(fn (array $record): string => __('addon-manager::addons.install_name', ['name' => $record['name']]))
            ->modalDescription(fn (array $record): ?string => $this->installModalDescription($record))
            ->modalSubmitActionLabel(__('addon-manager::addons.install'))
            ->schema([
                Toggle::make('run_migrations')
                    ->label(__('addon-manager::addons.run_migrations'))
                    ->default(true),
            ])
            ->authorize(fn (): bool => static::canEdit())
            ->visible(fn (array $record): bool => $this->canInstall($record))
            ->disabled(fn (array $record): bool => $this->installBlockedReason($record) !== null)
            ->tooltip(fn (array $record): ?string => $this->installBlockedReason($record))
            ->action(function (array $data, array $record): void {
                if ($this->installBlockedReason($record) !== null) {
                    return;
                }

                $ranSync = InstallAddonJob::dispatchFor(
                    $record['id'],
                    $record['latest_version'],
                    (bool) ($data['run_migrations'] ?? true),
                    (int) Auth::id(),
                );

                // Sync path: the job has already run to completion and sent its own
                // success/failure bell notification — don't add a contradictory
                // "installing now" on top of a failure. Re-fetch the page as a
                // controlled SPA navigation so a newly-installed module's Filament
                // pages/nav register and the list updates — WITHOUT the browser's
                // native "unsaved changes" prompt that an uncontrolled reload (from
                // the changed panel component registration) would raise.
                if ($ranSync) {
                    $this->redirect(static::getUrl(), navigate: true);

                    return;
                }

                InstallProgress::set($record['id'], 'queued', 5, __('addon-manager::addons.queued'));

                Notification::make()
                    ->title(__('addon-manager::addons.install_queued'))
                    ->body(__('addon-manager::addons.install_running_bg'))
                    ->success()
                    ->send();

                $this->refreshEntries();
            });
    }

    /**
     * Enable or disable an installed add-on. The primary slot carries it only
     * when there is no Install/Update to show; otherwise it lives in the menu.
     */
    private function toggleRecordAction(bool $enable, bool $inMenu): Action
    {
        $action = Action::make(($enable ? 'enable' : 'disable').($inMenu ? 'FromMenu' : ''))
            ->label($enable ? __('addon-manager::addons.enable') : __('addon-manager::addons.disable'))
            ->icon(Phosphor::PowerLight)
            ->color('gray')
            ->authorize(fn (): bool => static::canEdit())
            ->visible(fn (array $record): bool => $this->canManage($record)
                && $record['enabled'] !== $enable
                && $this->canInstall($record) === $inMenu)
            ->action(function (array $record) use ($enable): void {
                $registry = app(AddonRegistry::class);
                $enable ? $registry->enable($record['installed_key']) : $registry->disable($record['installed_key']);

                // No redirect: the cards re-render reactively.
                $this->refreshEntries();
            });

        return $inMenu ? $action : $action->button()->size(Size::Small);
    }

    /**
     * Delete an installed add-on, optionally dropping its tables.
     */
    private function deleteRecordAction(): Action
    {
        return Action::make('delete')
            ->label(__('filament-actions::delete.single.label'))
            ->icon(Phosphor::TrashLight)
            ->color('danger')
            ->requiresConfirmation()
            ->schema([
                Checkbox::make('remove_tables')
                    ->label(__('filament.addon_remove_tables'))
                    ->helperText(__('filament.addon_remove_tables_help'))
                    ->default(false),
            ])
            ->authorize(fn (): bool => static::canEdit())
            ->visible(fn (array $record): bool => $this->canManage($record))
            ->action(function (array $record, array $data): void {
                $outcome = app(AddonRegistry::class)->delete(
                    (string) $record['installed_key'],
                    (bool) ($data['remove_tables'] ?? false),
                );

                // Three outcomes, and they are genuinely different things: an
                // uninstall, a disable (a linked addon's files are not in the
                // addon root), and a revert to the bundled version. Reporting any
                // of them as another misdescribes what just happened to the
                // operator's install.
                match ($outcome) {
                    AddonDeleteOutcome::Removed => Notification::make()
                        ->title(__('addon-manager::addons.addon_deleted'))
                        ->success()
                        ->send(),
                    AddonDeleteOutcome::Reverted => Notification::make()
                        ->title(__('addon-manager::addons.addon_reverted'))
                        ->body(__('addon-manager::addons.addon_reverted_body'))
                        ->success()
                        ->send(),
                    AddonDeleteOutcome::Disabled => Notification::make()
                        ->title(__('addon-manager::addons.addon_disabled_not_removed'))
                        ->body(__('addon-manager::addons.addon_ships_with_phpvms'))
                        ->warning()
                        ->send(),
                    AddonDeleteOutcome::NotFound => null,
                };

                $this->refreshEntries();
            });
    }

    private function repositoryRecordAction(): Action
    {
        return Action::make('repository')
            ->label(__('addon-manager::addons.repository_link'))
            ->icon(Phosphor::ArrowSquareOutLight)
            ->authorize(fn (): bool => static::canAccess())
            ->visible(fn (array $record): bool => filled($record['repository_url']))
            ->url(fn (array $record): string => $record['repository_url'])
            ->openUrlInNewTab();
    }

    /**
     * The slide-over detail a card click opens. Read-only; the buttons inside
     * re-mount the card's own actions (see peekButtons()).
     */
    private function peekRecordAction(): Action
    {
        return Action::make('peek')
            ->label(__('addon-manager::addons.details'))
            ->icon(Phosphor::EyeLight)
            ->slideOver()
            // Read-only: nothing to lose by leaving, so skip the leave-page prompt.
            ->unsavedChangesAlert(false)
            ->modalWidth(Width::ThreeExtraLarge)
            ->modalHeading(fn (array $record): string => $record['name'])
            ->modalSubmitAction(false)
            ->modalCancelAction(false)
            ->modalFooterActionsAlignment(Alignment::Start)
            ->extraModalWindowAttributes(['class' => 'pirep-panel addon-panel'])
            ->extraModalOverlayAttributes(['class' => 'pirep-panel-overlay'])
            ->modalFooterActions([])
            ->authorize(fn (): bool => static::canAccess())
            ->modalContent(function (array $record): Htmlable {
                $row = $this->allEntries()->firstWhere('id', $record['id']) ?? $record;

                // Release history is a best-effort registry call, only meaningful
                // for catalog packages. Skip it for local/installed-only addons and
                // when the catalog is stale so opening a card stays instant.
                $releases = ($row['in_catalog'] && !$this->catalogState()['stale'])
                    ? (app(RegistryClient::class)->releaseMetadata($row['id'])['releases'] ?? [])
                    : [];

                return view('addon-manager::filament.addons.peek', [
                    'row'      => $row,
                    'releases' => is_array($releases) ? $releases : [],
                    'buttons'  => $this->peekButtons($row),
                    'badges'   => $this->badgeData($row),
                    'progress' => InstallProgress::isActive($row['id']) ? InstallProgress::get($row['id']) : null,
                    'host'     => parse_url((string) config('addon-manager.registry_url'), PHP_URL_HOST) ?: 'the registry',
                ]);
            });
    }

    /**
     * The card's actions as buttons for the peek header/footer, each re-mounting
     * the real table action (so authorization, confirmation and modals are the
     * same ones the card uses). Empty when the user may not edit.
     *
     * @param  array<string, mixed>                                                                                            $row
     * @return array<int, array{name: string, label: string, icon: Phosphor, color: string, disabled: bool, tooltip: ?string}>
     */
    private function peekButtons(array $row): array
    {
        if (!static::canEdit()) {
            return [];
        }

        $buttons = [];

        if ($this->canInstall($row)) {
            $reason = $this->installBlockedReason($row);
            $buttons[] = [
                'name'     => 'install',
                'label'    => $this->installLabel($row),
                'icon'     => $row['update_available'] ? Phosphor::ArrowsClockwiseLight : Phosphor::DownloadSimpleLight,
                'color'    => 'primary',
                'disabled' => $reason !== null,
                'tooltip'  => $reason,
            ];
        }

        if ($this->canManage($row)) {
            $buttons[] = [
                'name'     => ($row['enabled'] ? 'disable' : 'enable').($this->canInstall($row) ? 'FromMenu' : ''),
                'label'    => $row['enabled'] ? __('addon-manager::addons.disable') : __('addon-manager::addons.enable'),
                'icon'     => Phosphor::PowerLight,
                'color'    => 'gray',
                'disabled' => false,
                'tooltip'  => null,
            ];
            $buttons[] = [
                'name'     => 'delete',
                'label'    => __('filament-actions::delete.single.label'),
                'icon'     => Phosphor::TrashLight,
                'color'    => 'danger',
                'disabled' => false,
                'tooltip'  => null,
            ];
        }

        return $buttons;
    }

    /**
     * Read-only facts shown in the install modal: publisher, requirements + the
     * compatibility result and the verification note. Falls back to the
     * incompatibility reason.
     *
     * @param array<string, mixed> $row
     */
    private function installModalDescription(array $row): ?string
    {
        if (!$row['compatible']) {
            return $row['incompatible_reason'];
        }

        $parts = [];

        if ($row['publisher']) {
            $parts[] = __('addon-manager::addons.by_publisher', ['publisher' => $row['publisher']]);
        }

        $requires = array_filter([
            $row['min_php'] ? 'php ≥'.$row['min_php'] : null,
            $row['min_phpvms'] ? 'phpvms ≥'.$row['min_phpvms'] : null,
        ]);
        if ($requires !== []) {
            $parts[] = __('addon-manager::addons.requires_ok', ['req' => implode(' · ', $requires)]);
        }

        $parts[] = __('addon-manager::addons.verified_download');

        return implode(' · ', $parts);
    }

    /**
     * Upload a local / unlisted addon .zip through the existing ZipSource path.
     */
    public function uploadZipAction(): Action
    {
        return Action::make('uploadZip')
            ->label(__('addon-manager::addons.upload_zip'))
            ->color('gray')
            ->modalSubmitActionLabel(__('addon-manager::addons.install'))
            ->schema([
                FileUpload::make('zip')
                    ->label(__('addon-manager::addons.addon_package'))
                    ->acceptedFileTypes(['application/zip', 'application/x-zip-compressed'])
                    ->storeFiles(false)
                    ->required(),
            ])
            ->action(function (array $data): void {
                /** @var TemporaryUploadedFile $file */
                $file = $data['zip'];

                try {
                    app(AddonRegistry::class)->install(new ZipSource($file->getRealPath()));
                    Notification::make()->title(__('addon-manager::addons.addon_installed'))->success()->send();
                } catch (Throwable $throwable) {
                    Notification::make()->title(__('addon-manager::addons.install_failed'))->body($throwable->getMessage())->danger()->send();
                }
            });
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    /**
     * Keep only http(s) URLs; everything else (javascript:, data:, blank) is
     * dropped to an empty string so it renders as no link/image.
     */
    private function safeUrl(string $url): string
    {
        return str_starts_with($url, 'http://') || str_starts_with($url, 'https://') ? $url : '';
    }

    private function monogram(string $name): string
    {
        $words = preg_split('/[\s\-_\/]+/', trim($name)) ?: [];
        $words = array_values(array_filter($words));

        $letters = count($words) >= 2
            ? Str::substr($words[0], 0, 1).Str::substr($words[1], 0, 1)
            : Str::substr($name, 0, 2);

        return Str::upper($letters);
    }
}

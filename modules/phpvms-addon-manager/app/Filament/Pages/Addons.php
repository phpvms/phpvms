<?php

declare(strict_types=1);

namespace Modules\AddonManager\Filament\Pages;

use App\Addons\AddonRegistry;
use App\Addons\Services\AddonDiscoveryService;
use App\Addons\Sources\ZipSource;
use App\Enums\NavigationGroup;
use App\Filament\Concerns\AuthorizesAccess;
use App\Models\Addon;
use BackedEnum;
use Filafly\Icons\Phosphor\Enums\Phosphor;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Toggle;
use Filament\Navigation\NavigationItem;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
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

use function Filament\Support\original_request;

/**
 * Split-catalog addon manager: browse the registry, install/update from it, and
 * manage installed addons — replacing the core Addons page. Discovered into the
 * main admin panel by AdminPanelProvider's module-page discovery.
 *
 * The list/detail data is assembled in-memory each render by merging the cached
 * registry catalog (RegistryClient) with the installed addon rows; search,
 * category filter, tab, and selection are Livewire state.
 */
class Addons extends Page
{
    use AuthorizesAccess;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::AddOns;

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Phosphor::PuzzlePieceLight;

    /** Rows per page. Ten fills the column without making it the whole screen. */
    private const int PER_PAGE = 10;

    /**
     * The tab a bare /admin/addons lands on. It has to agree with the first
     * workspace tab, or arriving at the page highlights a tab you did not pick.
     * Installed leads because "why is that add-on not running" is the question
     * people come here with; browsing the registry is a deliberate second step.
     */
    private const string DEFAULT_TAB = 'installed';

    /**
     * Which population is listed. Bound to `?tab=` so the topbar's workspace
     * tabs can link straight to it; still a plain property, so `set()` works.
     */
    #[Url(as: 'tab')]
    public string $activeTab = self::DEFAULT_TAB;

    /** Enable state within the active tab: `all`, `enabled` or `disabled`. */
    public string $state = 'all';

    public string $search = '';

    public ?string $category = null;

    public ?string $selectedId = null;

    public int $page = 1;

    /**
     * Per-request memo for the assembled listing — allEntries() runs disk
     * discovery + a catalog read + a DB read and is consulted several times per
     * render (listing, counts, categories, selection). Fresh each request.
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

    #[Override]
    public static function getNavigationLabel(): string
    {
        return (string) Str::of(__('common.addons'))->plural();
    }

    /**
     * Three workspace tabs rather than one nav entry. The topbar navigator is
     * built from the active group's NavigationItems, so this is what puts
     * Installed / Updates / Registry up there instead of stacking a second tab
     * row inside the page.
     *
     * Every badge here renders on EVERY admin page, so all three stay cheap: one
     * count query, one cached-catalog read, and the existing cache-only update
     * count. None of them may reach the network or scan the disk.
     *
     * @return array<int, NavigationItem>
     */
    #[Override]
    public static function getNavigationItems(): array
    {
        $tabs = [
            'installed' => [__('addon-manager::addons.installed_tab'), Phosphor::PuzzlePieceLight, fn (): ?string => self::badge(Addon::query()->count())],
            'updates'   => [__('addon-manager::addons.updates'), Phosphor::DownloadSimpleLight, fn (): ?string => self::badge(app(static::class)->updateCount())],
            'browse'    => [__('addon-manager::addons.registry_tab'), Phosphor::MagnifyingGlassLight, fn (): ?string => self::badge(count(app(RegistryClient::class)->cachedCatalog()['entries']))],
        ];

        $sort = static::getNavigationSort() ?? 0;
        $items = [];

        foreach ($tabs as $tab => [$label, $icon, $badge]) {
            $items[] = NavigationItem::make($label)
                ->key(static::class.':'.$tab)
                ->group(static::getNavigationGroup())
                ->icon($icon)
                ->badge($badge, color: $tab === 'updates' ? 'warning' : null)
                ->sort($sort++)
                ->isActiveWhen(fn (): bool => original_request()->routeIs(static::getRouteName())
                    && original_request()->query('tab', self::DEFAULT_TAB) === $tab)
                ->url(static::getUrl(['tab' => $tab]));
        }

        return $items;
    }

    /** A zero count is not news; leave the tab unbadged. */
    private static function badge(int $count): ?string
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
            'listed'   => $this->tabCounts()[$this->activeTab],
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
     * List left, detail right. Both halves are Sections so they inherit the
     * panel's card, header band and density; the split is the schema Grid's own
     * column spans rather than a hand-rolled two-column CSS grid.
     */
    #[Override]
    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('addon-manager::filament.addons.notice')
                ->visible(fn (): bool => $this->catalogState()['stale'] || filled($this->catalogState()['error'])),

            // One column of 430px and one of whatever is left is not a ratio the
            // 12-column grid can express, so `.catalog` supplies the template and
            // the Grid just holds the two cards.
            Grid::make()
                ->columns(1)
                ->extraAttributes(['class' => 'catalog'])
                ->schema([
                    Section::make()
                        ->schema([
                            View::make('addon-manager::filament.addons.toolbar'),
                            View::make('addon-manager::filament.addons.list'),
                        ]),

                    Section::make()
                        ->extraAttributes(['class' => 'catalog__detail'])
                        ->schema([
                            View::make('addon-manager::filament.addons.empty')
                                ->visible(fn (): bool => $this->selected() === null),

                            View::make('addon-manager::filament.addons.hero')
                                ->visible(fn (): bool => $this->selected() !== null),

                            View::make('addon-manager::filament.addons.register')
                                ->visible(fn (): bool => $this->selected() !== null),

                            // Nested Section: renders as a sequent head band
                            // inside the same card, not a second card.
                            Section::make(__('addon-manager::addons.releases'))
                                ->icon(Phosphor::ClockCounterClockwiseLight)
                                ->collapsible()
                                ->persistCollapsed()
                                ->visible(fn (): bool => $this->releases() !== [])
                                ->schema([
                                    View::make('addon-manager::filament.addons.releases'),
                                ]),

                            View::make('addon-manager::filament.addons.verify')
                                ->visible(fn (): bool => $this->selected() !== null),
                        ]),
                ]),
        ]);
    }

    /**
     * Release history for the selection, newest first. Best-effort: the registry
     * call behind it is allowed to fail, and an empty list hides the section.
     *
     * @return array<int, array<string, mixed>>
     */
    public function releases(): array
    {
        $releases = $this->selected()['release']['releases'] ?? null;

        return is_array($releases) ? $releases : [];
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
            // Published by the phpVMS project rather than a third party. Sorts
            // first and earns a star; never affects whether it can be installed.
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
            'changelog_url'       => $this->safeUrl((string) ($entry['changelog_url'] ?? '')),
            'icon'                => $this->safeUrl((string) ($entry['icon'] ?? '')) ?: null,
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
     * The current tab's rows: filtered by search + category and sorted with
     * incompatible entries greyed and pushed last.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function listing(): Collection
    {
        $rows = $this->allEntries();

        $rows = match ($this->activeTab) {
            'updates'   => $rows->where('update_available', true),
            'installed' => $rows->where('installed', true),
            default     => $rows->where('in_catalog', true),
        };

        $rows = match ($this->state) {
            'enabled'  => $rows->where('installed', true)->where('enabled', true),
            'disabled' => $rows->where('installed', true)->where('enabled', false),
            default    => $rows,
        };

        if (filled($this->category)) {
            $rows = $rows->where('category', $this->category);
        }

        $searching = filled($this->search);

        if ($searching) {
            $needle = Str::lower($this->search);
            $rows = $rows->filter(fn (array $row): bool => str_contains(Str::lower($row['name']), $needle)
                || str_contains(Str::lower($row['description']), $needle)
                || str_contains(Str::lower($row['id']), $needle));
        }

        return $rows
            ->sortBy([
                fn (array $a, array $b): int => ($a['compatible'] ? 0 : 1) <=> ($b['compatible'] ? 0 : 1),
                // Official add-ons lead the shelf. Not while searching: someone
                // who typed a name wants the best match first, and demoting a
                // community add-on that matched better would be a lookup bug.
                fn (array $a, array $b): int => $searching
                    ? 0
                    : (($a['official'] ? 0 : 1) <=> ($b['official'] ? 0 : 1)),
                fn (array $a, array $b): int => strcasecmp((string) $a['name'], (string) $b['name']),
            ])
            ->values();
    }

    /**
     * Rows on the current page, plus what the footer needs to describe them.
     * Named `paginator` because `$page` is already the page-number property.
     */
    public function paginator(): LengthAwarePaginator
    {
        $rows = $this->listing();

        return new LengthAwarePaginator(
            $rows->forPage($this->page, self::PER_PAGE)->values(),
            $rows->count(),
            self::PER_PAGE,
            $this->page,
        );
    }

    /**
     * The selected detail row (falls back to the first listed), enriched with
     * lazily-fetched release metadata. Null when the listing is empty.
     *
     * @return array<string, mixed>|null
     */
    public function selected(): ?array
    {
        $listing = $this->listing();

        $row = $listing->firstWhere('id', $this->selectedId) ?? $listing->first();

        if ($row === null) {
            return null;
        }

        // Release history is a best-effort registry call, only meaningful for
        // catalog packages. Skip it for local/installed-only addons and when the
        // catalog is stale (registry unreachable) so selecting a row stays instant
        // instead of blocking on a request that returns nothing.
        $row['release'] = ($row['in_catalog'] && !$this->catalogState()['stale'])
            ? app(RegistryClient::class)->releaseMetadata($row['id'])
            : null;
        $row['progress'] = InstallProgress::get($row['id']);

        return $row;
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
     * Any narrowing of the list invalidates the page number — page 3 of a
     * five-row result is an empty column with no way back.
     */
    public function updated(string $property): void
    {
        if (in_array($property, ['activeTab', 'state', 'search', 'category'], true)) {
            $this->page = 1;
        }
    }

    /**
     * Counts for the enable-state tabs, scoped to the active tab so the numbers
     * describe the list actually on screen.
     *
     * @return array{all: int, enabled: int, disabled: int}
     */
    public function stateCounts(): array
    {
        $rows = match ($this->activeTab) {
            'updates'   => $this->allEntries()->where('update_available', true),
            'installed' => $this->allEntries()->where('installed', true),
            default     => $this->allEntries()->where('in_catalog', true),
        };

        return [
            'all'      => $rows->count(),
            'enabled'  => $rows->where('installed', true)->where('enabled', true)->count(),
            'disabled' => $rows->where('installed', true)->where('enabled', false)->count(),
        ];
    }

    /**
     * @return array{browse: int, updates: int, installed: int}
     */
    public function tabCounts(): array
    {
        $all = $this->allEntries();

        return [
            'browse'    => $all->where('in_catalog', true)->count(),
            'updates'   => $all->where('update_available', true)->count(),
            'installed' => $all->where('installed', true)->count(),
        ];
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

    // ── Row interactions ──────────────────────────────────────────────────

    public function select(string $id): void
    {
        $this->selectedId = $id;
    }

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

    public function enable(string $key): void
    {
        app(AddonRegistry::class)->enable($key);
        // No redirect: the component re-renders reactively and the list reflects
        // the new state. A full redirect is what made this feel like a page load.
    }

    public function disable(string $key): void
    {
        app(AddonRegistry::class)->disable($key);
    }

    // ── Actions ───────────────────────────────────────────────────────────

    /**
     * Install / update the selected addon. The trigger label is contextual
     * ("Install" vs "Update to vX.Y.Z"); the modal shows publisher, requirements
     * with the compatibility result, package size and a verification note above a
     * migrations toggle. Disabled (with a reason) when incompatible or when the
     * catalog carries no installable version.
     */
    public function installAction(): Action
    {
        return Action::make('install')
            ->label(fn (): string => ($this->selected()['update_available'] ?? false)
                ? __('addon-manager::addons.update_to', ['version' => $this->selected()['latest_version'] ?? ''])
                : __('addon-manager::addons.install'))
            ->modalHeading(fn (): string => __('addon-manager::addons.install_name', ['name' => $this->selected()['name'] ?? '']))
            ->modalDescription(fn (): ?string => $this->installModalDescription())
            ->modalSubmitActionLabel(__('addon-manager::addons.install'))
            ->schema([
                Toggle::make('run_migrations')
                    ->label(__('addon-manager::addons.run_migrations'))
                    ->default(true),
            ])
            ->disabled(fn (): bool => !$this->isInstallable($this->selected()))
            ->action(function (array $data): void {
                $row = $this->selected();

                if (!$this->isInstallable($row)) {
                    return;
                }

                $ranSync = InstallAddonJob::dispatchFor(
                    $row['id'],
                    $row['latest_version'],
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

                InstallProgress::set($row['id'], 'queued', 5, __('addon-manager::addons.queued'));

                Notification::make()
                    ->title(__('addon-manager::addons.install_queued'))
                    ->body(__('addon-manager::addons.install_running_bg'))
                    ->success()
                    ->send();
            });
    }

    /**
     * True when the selected/target row can be installed: present, compatible,
     * and the catalog carries a non-empty version to mint.
     *
     * @param array<string, mixed>|null $row
     */
    private function isInstallable(?array $row): bool
    {
        return $row !== null && $row['compatible'] && ($row['latest_version'] ?? '') !== '';
    }

    /**
     * Read-only facts shown in the install modal: publisher, requirements + the
     * compatibility result, package size (when the registry reports it) and the
     * verification note. Falls back to the incompatibility reason.
     */
    private function installModalDescription(): ?string
    {
        $row = $this->selected();

        if ($row === null) {
            return null;
        }

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

        $size = $row['release']['size'] ?? null;
        if (is_numeric($size)) {
            $parts[] = __('addon-manager::addons.size', ['size' => $this->humanBytes((int) $size)]);
        }

        $parts[] = __('addon-manager::addons.verified_download');

        return implode(' · ', $parts);
    }

    private function humanBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        $units = ['KB', 'MB', 'GB'];
        $value = $bytes / 1024;
        $unit = 0;

        while ($value >= 1024 && $unit < count($units) - 1) {
            $value /= 1024;
            $unit++;
        }

        return round($value, 1).' '.$units[$unit];
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

    /**
     * Delete an installed addon, optionally dropping its tables.
     */
    public function deleteAction(): Action
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
            ->action(function (array $arguments, array $data): void {
                app(AddonRegistry::class)->delete(
                    (string) $arguments['key'],
                    (bool) ($data['remove_tables'] ?? false),
                );
                // Clear the selection so the detail pane falls back to the first
                // row; the component re-renders reactively (no page reload).
                $this->selectedId = null;
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

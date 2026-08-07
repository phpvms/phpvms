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
use Daljo25\FilamentTablerIcons\Enums\TablerIcon;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Modules\AddonManager\Jobs\InstallAddonJob;
use Modules\AddonManager\Services\CompatibilityEvaluator;
use Modules\AddonManager\Services\RegistryClient;
use Modules\AddonManager\Support\InstallProgress;
use Override;
use Throwable;
use UnitEnum;

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

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Config;

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = TablerIcon::Puzzle;

    protected string $view = 'addon-manager::filament.pages.addons';

    public string $activeTab = 'browse';

    public string $search = '';

    public ?string $category = null;

    public ?string $selectedId = null;

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

    #[Override]
    public static function getNavigationBadge(): ?string
    {
        $count = app(static::class)->updateCount();

        return $count > 0 ? (string) $count : null;
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
            // Registry-supplied URLs are rendered as hrefs; drop any non-http(s)
            // scheme (e.g. javascript:) so a hostile registry can't inject one.
            'repository_url'      => $this->safeUrl((string) ($entry['repository_url'] ?? '')),
            'icon'                => $this->safeUrl((string) ($entry['icon'] ?? '')) ?: null,
            'monogram'            => $this->monogram((string) $entry['name']),
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

        if (filled($this->category)) {
            $rows = $rows->where('category', $this->category);
        }

        if (filled($this->search)) {
            $needle = Str::lower($this->search);
            $rows = $rows->filter(fn (array $row): bool => str_contains(Str::lower($row['name']), $needle)
                || str_contains(Str::lower($row['description']), $needle)
                || str_contains(Str::lower($row['id']), $needle));
        }

        return $rows
            ->sortBy([
                fn (array $a, array $b): int => ($a['compatible'] ? 0 : 1) <=> ($b['compatible'] ? 0 : 1),
                fn (array $a, array $b): int => strcasecmp((string) $a['name'], (string) $b['name']),
            ])
            ->values();
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
            ->icon(TablerIcon::Trash)
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

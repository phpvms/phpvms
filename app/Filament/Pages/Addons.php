<?php

namespace App\Filament\Pages;

use App\Addons\AddonRegistry;
use App\Addons\Services\AddonDiscoveryService;
use App\Enums\NavigationGroup;
use App\Filament\Concerns\AuthorizesAccess;
use App\Models\Addon;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Override;
use UnitEnum;

class Addons extends Page implements HasTable
{
    use AuthorizesAccess;
    use InteractsWithTable;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Developers;

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPuzzlePiece;

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
        return Str::of(__('common.addons'))->plural();
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('common.name'))
                    ->sortable(),

                IconColumn::make('enabled')
                    ->label(__('common.enabled'))
                    ->boolean()
                    ->sortable(),
            ])
            ->searchable()
            ->recordActions([
                Action::make('enable')
                    ->label(__('common.enable'))
                    ->color('success')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->visible(fn (array $record): bool => !$record['enabled'])
                    ->action(function (array $record): void {
                        app(AddonRegistry::class)->enable($record['key']);
                        $this->redirectRoute('filament.admin.pages.addons');
                    }),

                Action::make('disable')
                    ->label(__('common.disable'))
                    ->color('warning')
                    ->icon(Heroicon::OutlinedMinusCircle)
                    ->visible(fn (array $record): bool => $record['enabled'])
                    ->action(function (array $record): void {
                        app(AddonRegistry::class)->disable($record['key']);
                        $this->redirectRoute('filament.admin.pages.addons');
                    }),

                Action::make('delete')
                    ->label(__('filament-actions::delete.single.label'))
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->visible(fn (array $record): bool => !$record['enabled'])
                    ->requiresConfirmation()
                    ->schema([
                        Checkbox::make('remove_tables')
                            ->label(__('filament.addon_remove_tables'))
                            ->helperText(__('filament.addon_remove_tables_help'))
                            ->default(false),
                    ])
                    ->action(function (array $record, array $data): void {
                        app(AddonRegistry::class)->delete(
                            $record['key'],
                            (bool) ($data['remove_tables'] ?? false),
                        );
                        $this->redirectRoute('filament.admin.pages.addons');
                    }),
            ])
            ->records(fn (?string $sortColumn, ?string $sortDirection, ?string $search): Collection => $this->getModulesRecords()
                ->when(
                    filled($sortColumn),
                    fn (Collection $data): Collection => $data->sortBy(
                        $sortColumn,
                        SORT_REGULAR,
                        $sortDirection === 'desc',
                    )
                )
                ->when(
                    filled($search),
                    fn (Collection $data): Collection => $data->filter(
                        fn (array $record): bool => str_contains(
                            Str::lower($record['name']),
                            Str::lower($search),
                        ),
                    ),
                )
            );
    }

    #[Override]
    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                EmbeddedTable::make(),
            ]);
    }

    public function getModulesRecords(): Collection
    {
        // Detect addons that are present on disk but have no DB row — freshly
        // uploaded (e.g. via FTP) or uninstalled/deleted while their files
        // remain — so they resurface here as installable (disabled) entries.
        // Idempotent: existing rows are skipped, new ones are inserted disabled
        // without touching the boot cache. The boot-cache prime alone can't do
        // this: after a panel delete it rewrites a fresh cache, so the next boot
        // short-circuits discovery and the on-disk addon is never re-detected.
        app(AddonDiscoveryService::class)->discoverNewAddons();

        return app(AddonRegistry::class)->all()->map(fn (Addon $addon): array => [
            // Canonical, machine-readable name used to resolve the addon in the
            // registry (enable/disable/delete). Kept separate from the display
            // label below, which may be decorated with the registry id.
            'key'     => $addon->getName(),
            'name'    => empty($addon->registry_id) ? $addon->getName() : $addon->registry_id.'('.$addon->getName().')',
            'enabled' => $addon->isEnabled(),
        ])->values();
    }
}

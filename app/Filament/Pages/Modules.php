<?php

namespace App\Filament\Pages;

use App\Models\Enums\NavigationGroup;
use App\Services\ModuleService;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Nwidart\Modules\Facades\Module;

class Modules extends Page implements Tables\Contracts\HasTable
{
    use HasPageShield;
    use Tables\Concerns\InteractsWithTable;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Developers;

    protected static ?int $navigationSort = 1;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedPuzzlePiece;

    public static function getNavigationLabel(): string
    {
        return Str::of(__('common.module'))->plural();
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
                        app(ModuleService::class)->updateModule($record['name'], true);
                        $this->redirectRoute('filament.admin.pages.modules'); // Reload the page to refresh everything
                    }),

                Action::make('disable')
                    ->label(__('common.disable'))
                    ->color('warning')
                    ->icon(Heroicon::OutlinedMinusCircle)
                    ->visible(fn (array $record): bool => $record['enabled'])
                    ->action(function (array $record): void {
                        app(ModuleService::class)->updateModule($record['name'], false);
                        $this->redirectRoute('filament.admin.pages.modules'); // Reload the page to refresh everything
                    }),

                Action::make('delete')
                    ->label(__('filament-actions::delete.single.label'))
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->visible(fn (array $record): bool => !$record['enabled'])
                    ->requiresConfirmation()
                    ->action(function (array $record): void {
                        app(ModuleService::class)->deleteModule($record['name']);
                        $this->redirectRoute('filament.admin.pages.modules'); // Reload the page to refresh everything
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

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                EmbeddedTable::make(),
            ]);
    }

    public function getModulesRecords(): Collection
    {
        $modulesStatuses = [];

        foreach (Module::all() as $module) {
            $modulesStatuses[] = [
                'name'    => $module->getName(),
                'enabled' => $module->isEnabled(),
            ];
        }

        return collect($modulesStatuses);
    }
}

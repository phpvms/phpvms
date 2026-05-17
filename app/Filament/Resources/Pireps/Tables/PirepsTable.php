<?php

namespace App\Filament\Resources\Pireps\Tables;

use App\Enums\PirepState;
use App\Filament\Resources\Pireps\Actions\AcceptAction;
use App\Filament\Resources\Pireps\Actions\RejectAction;
use App\Models\Airport;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Table configuration for the PIREP list page.
 *
 * NOTE: The list page (ListPireps) overrides `$view` and `content()` to
 * render pireps as custom cards instead of an embedded table. The Table
 * object here is used only as a query/filter/pagination machine — its
 * columns are intentionally empty (Filament requires at least one
 * sortable column for the toolbar). Actions defined below are mounted
 * by the custom blade per-row via `mountTableAction()`.
 */
class PirepsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with(['airline', 'aircraft', 'user', 'dpt_airport:id,icao,name', 'arr_airport:id,icao,name'])
                ->whereNotIn('state', [PirepState::DRAFT, PirepState::IN_PROGRESS, PirepState::CANCELLED]))
            ->columns([
                // Empty placeholder column — the custom blade view renders rows itself.
                // Filament needs at least one column for default sort/search wiring.
                TextColumn::make('submitted_at')
                    ->hidden(),
            ])
            ->paginated([25])
            ->defaultPaginationPageOption(25)
            ->defaultSort('submitted_at', 'desc')
            ->searchable()
            ->filters([
                SelectFilter::make('state')
                    ->label(__('common.state'))
                    ->options(collect(PirepState::cases())
                        ->reject(fn (PirepState $state): bool => in_array($state, [PirepState::DRAFT, PirepState::IN_PROGRESS, PirepState::CANCELLED], true))
                        ->mapWithKeys(fn (PirepState $state): array => [$state->value => $state->getLabel()])
                        ->all()),

                SelectFilter::make('airline')
                    ->relationship('airline', 'name')
                    ->label(__('common.airline'))
                    ->searchable()
                    ->preload(),

                SelectFilter::make('user')
                    ->relationship('user', 'name')
                    ->label(trans_choice('common.user', 1))
                    ->searchable()
                    ->preload(),

                SelectFilter::make('dpt_airport')
                    ->label(__('airports.departure'))
                    ->relationship('dpt_airport', 'icao')
                    ->getOptionLabelFromRecordUsing(fn (Airport $record): string => $record->icao.' - '.$record->name)
                    ->searchable()
                    ->preload(),

                SelectFilter::make('arr_airport')
                    ->label(__('airports.arrival'))
                    ->relationship('arr_airport', 'icao')
                    ->getOptionLabelFromRecordUsing(fn (Airport $record): string => $record->icao.' - '.$record->name)
                    ->searchable()
                    ->preload(),

                Filter::make('submitted_at')
                    ->schema([
                        DatePicker::make('filed_after')
                            ->label(__('filament.filed_after')),
                        DatePicker::make('filed_before')
                            ->label(__('filament.filed_before')),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when(
                            isset($data['filed_after']) && $data['filed_after'],
                            fn (Builder $query): Builder => $query->whereDate('submitted_at', '>=', $data['filed_after']),
                        )
                        ->when(
                            isset($data['filed_before']) && $data['filed_before'],
                            fn (Builder $query): Builder => $query->whereDate('submitted_at', '<=', $data['filed_before']),
                        )),
                TrashedFilter::make(),
            ])
            ->filtersLayout(FiltersLayout::Modal)
            ->filtersFormColumns(2)
            ->persistFiltersInSession()
            ->recordActions([
                AcceptAction::make(),
                RejectAction::make(),
                EditAction::make(),
                DeleteAction::make(),
                ForceDeleteAction::make(),
                RestoreAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}

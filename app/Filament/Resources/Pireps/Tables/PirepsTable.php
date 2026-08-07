<?php

namespace App\Filament\Resources\Pireps\Tables;

use App\Enums\PirepState;
use App\Filament\Resources\Pireps\Actions\AcceptAction;
use App\Filament\Resources\Pireps\Actions\RejectAction;
use App\Filament\Resources\Pireps\PirepResource;
use App\Models\Airport;
use App\Models\Pirep;
use App\Support\Units\Time;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Support\Enums\FontFamily;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Table configuration for the PIREP list page.
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
                TextColumn::make('ident')
                    ->label(trans_choice('common.flight', 1))
                    ->fontFamily(FontFamily::Mono)
                    ->url(fn (Pirep $record): string => PirepResource::getUrl('view', ['record' => $record]))
                    ->searchable(['flight_number'])
                    ->sortable(['flight_number']),

                TextColumn::make('user.name')
                    ->label(trans_choice('common.pilot', 1))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('route')
                    ->label(__('flights.route'))
                    ->fontFamily(FontFamily::Mono)
                    ->state(fn (Pirep $record): string => trim($record->dpt_airport_id.' → '.$record->arr_airport_id)),

                TextColumn::make('aircraft.registration')
                    ->label(__('pireps.tail'))
                    ->fontFamily(FontFamily::Mono),

                TextColumn::make('flight_time')
                    ->label(__('pireps.block'))
                    ->fontFamily(FontFamily::Mono)
                    ->alignEnd()
                    ->formatStateUsing(function (?int $state): string {
                        $hm = Time::minutesToTimeParts($state ?? 0);

                        return sprintf('%d:%02d', $hm['h'], $hm['m']);
                    }),

                TextColumn::make('landing_rate')
                    ->label(__('pireps.landing'))
                    ->fontFamily(FontFamily::Mono)
                    ->alignEnd()
                    ->formatStateUsing(fn (int|float|null $state): string => $state !== null && (int) $state !== 0 ? number_format((float) $state) : '—')
                    ->tooltip(fn (int|float|null $state): ?string => $state !== null && (int) $state !== 0 ? number_format((float) $state).' fpm' : null)
                    ->color(fn (int|float|null $state): ?string => match (true) {
                        $state === null || (int) $state === 0 => 'gray',
                        $state > 0, $state <= -400            => 'danger',
                        $state <= -250                        => 'warning',
                        $state > -150                         => 'success',
                        default                               => null,
                    }),

                TextColumn::make('state')
                    ->label(__('common.state'))
                    ->badge()
                    ->sortable(),

                TextColumn::make('submitted_at')
                    ->label(__('pireps.filed'))
                    ->fontFamily(FontFamily::Mono)
                    ->alignEnd()
                    ->dateTime('j M H:i')
                    ->dateTimeTooltip()
                    ->sortable(),
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

                // Deep-linked from the dashboard activity calendar: /admin/pireps?departed_from=...&departed_to=...
                Filter::make('departed')
                    ->label(__('filament.departed_between'))
                    ->schema([
                        DateTimePicker::make('from')
                            ->label(__('filament.departed_from')),
                        DateTimePicker::make('to')
                            ->label(__('filament.departed_to')),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when(
                            filled($data['from'] ?? null),
                            fn (Builder $query): Builder => $query->where('block_off_time', '>=', $data['from']),
                        )
                        ->when(
                            filled($data['to'] ?? null),
                            fn (Builder $query): Builder => $query->where('block_off_time', '<=', $data['to']),
                        )),
            ])
            ->filtersLayout(FiltersLayout::Modal)
            ->filtersFormColumns(2)
            ->persistFiltersInSession()
            ->recordActions([
                AcceptAction::make(),
                RejectAction::make(),
                ActionGroup::make([
                    EditAction::make(),
                    DeleteAction::make(),
                    ForceDeleteAction::make(),
                    RestoreAction::make(),
                ]),
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

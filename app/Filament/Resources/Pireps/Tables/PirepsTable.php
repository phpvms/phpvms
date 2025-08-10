<?php

namespace App\Filament\Resources\Pireps\Tables;

use App\Filament\Resources\Pireps\Actions\AcceptAction;
use App\Filament\Resources\Pireps\Actions\RejectAction;
use App\Filament\Resources\Pireps\PirepResource;
use App\Filament\Resources\UserResource;
use App\Models\Airport;
use App\Models\Enums\PirepSource;
use App\Models\Enums\PirepState;
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
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PirepsTable
{
    public static function configure(Table $table): Table
    {

        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereNotIn('state', [PirepState::DRAFT, PirepState::IN_PROGRESS, PirepState::CANCELLED]))
            ->columns([
                TextColumn::make('ident')
                    ->label(trans_choice('common.flight', 1).' #')
                    ->searchable(['flight_number'])
                    ->sortable(),

                TextColumn::make('user.name')
                    ->url(fn (Pirep $record): string => UserResource::getUrl('edit', ['record' => $record->user]))
                    ->label(trans_choice('common.user', 1))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('dpt_airport_id')
                    ->label(__('flights.dep'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('arr_airport_id')
                    ->label(__('flights.arr'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('flight_time')
                    ->toggleable()
                    ->label(__('flights.flighttime'))
                    ->formatStateUsing(fn (int $state): string => Time::minutesToTimeString($state))
                    ->sortable(),

                TextColumn::make('aircraft')
                    ->toggleable()
                    ->label(__('common.aircraft'))
                    ->formatStateUsing(fn (Pirep $record): string => $record->aircraft->registration.' - '.$record->aircraft->name)
                    ->sortable(),

                TextColumn::make('source')
                    ->label(__('pireps.source'))
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->formatStateUsing(fn (int $state): string => PirepSource::label($state))
                    ->sortable(),

                TextColumn::make('state')
                    ->label(__('common.state'))
                    ->badge()
                    ->color(fn (int $state): string => match ($state) {
                        PirepState::PENDING  => 'warning',
                        PirepState::ACCEPTED => 'success',
                        PirepState::REJECTED => 'danger',
                        default              => 'info',
                    })
                    ->formatStateUsing(fn (int $state): string => PirepState::label($state))
                    ->sortable(),

                TextColumn::make('submitted_at')
                    ->since()
                    ->dateTooltip('d-m-Y H:i')
                    ->toggleable()
                    ->label(__('pireps.submitted'))
                    ->sortable(),
            ])
            ->defaultSort('submitted_at', 'desc')
            ->filters([
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
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                isset($data['filed_after']) && $data['filed_after'],
                                fn (Builder $query, $date): Builder => $query->whereDate('submitted_at', '>=', $date),
                            )
                            ->when(
                                isset($data['filed_before']) && $data['filed_before'],
                                fn (Builder $query, $date): Builder => $query->whereDate('submitted_at', '<=', $date),
                            );
                    }),
                TrashedFilter::make(),
            ])
            ->filtersFormColumns(2)
            ->recordUrl(fn (Pirep $record): string => PirepResource::getUrl('edit', ['record' => $record]))
            ->recordActions([
                ActionGroup::make([
                    AcceptAction::make(),
                    RejectAction::make(),

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

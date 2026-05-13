<?php

namespace App\Filament\Resources\Subfleets\Resources\Aircraft\Tables;

use App\Models\Aircraft;
use App\Models\Airport;
use App\Models\File;
use App\Services\FileService;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class AircraftTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('registration')
                    ->label(__('aircraft.registration'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name')
                    ->label(__('common.name'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('fin')
                    ->label('FIN')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),

                TextColumn::make('hub_id')
                    ->label(__('airports.home'))
                    ->toggleable()
                    ->searchable()
                    ->sortable(),

                TextColumn::make('airport_id')
                    ->label(__('airports.current'))
                    ->toggleable()
                    ->searchable()
                    ->sortable(),

                TextColumn::make('landingTime')
                    ->label(__('aircraft.last_landing'))
                    ->since()
                    ->dateTooltip('d/m/Y H:i')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),

                TextColumn::make('flight_time')
                    ->label(__('flights.flighttime'))
                    ->toggleable()
                    ->formatStateUsing(fn (int $state): string => floor($state / 60).'h'.$state % 60 .'min')
                    ->sortable(),

                TextColumn::make('status')
                    ->label(__('common.status'))
                    ->toggleable()
                    ->badge()
                    ->sortable(),

                TextColumn::make('state')
                    ->label(__('common.state'))
                    ->toggleable()
                    ->badge()
                    ->sortable(),

            ])
            ->filters([
                TrashedFilter::make(),

                SelectFilter::make('hub_id')
                    ->label(__('airports.home'))
                    ->relationship('home', 'icao')
                    ->getOptionLabelFromRecordUsing(fn (Airport $record): string => $record->icao.' - '.$record->name)
                    ->searchable()
                    ->preload(),

                SelectFilter::make('airport_id')
                    ->label(__('airports.current'))
                    ->relationship('airport', 'icao')
                    ->getOptionLabelFromRecordUsing(fn (Airport $record): string => $record->icao.' - '.$record->name)
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                ForceDeleteAction::make()->before(function (Aircraft $record): void {
                    $record->files()->each(function (File $file): void {
                        app(FileService::class)->removeFile($file);
                    });
                }),
                RestoreAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make()->before(function (Collection $records): void {
                        /** @var Collection<int, Aircraft> $records */
                        $records->each(fn (Aircraft $record) => $record->files()->each(function (File $file): void {
                            app(FileService::class)->removeFile($file);
                        }));
                    }),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->emptyStateActions([
            ]);
    }
}

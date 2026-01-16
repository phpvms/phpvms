<?php

namespace App\Filament\Resources\Subfleets\Tables;

use App\Models\Airport;
use App\Models\File;
use App\Models\Subfleet;
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

class SubfleetsTable
{
    public static function configure(Table $table): Table
    {

        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('common.name'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('airline.name')
                    ->label(__('common.airline'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('type')
                    ->label(__('common.type'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('hub_id')
                    ->label(__('airports.home'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('aircraft_count')
                    ->label(__('common.aircraft'))
                    ->counts('aircraft')
                    ->sortable(),
            ])
            ->filters([
                TrashedFilter::make(),

                SelectFilter::make('airline')
                    ->relationship('airline', 'name')
                    ->label(__('common.airline'))
                    ->searchable()
                    ->preload(),

                SelectFilter::make('hub_id')
                    ->label(__('airports.home'))
                    ->relationship('home', 'icao')
                    ->getOptionLabelFromRecordUsing(fn (Airport $record): string => $record->icao.' - '.$record->name)
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                ForceDeleteAction::make()->before(function (Subfleet $record) {
                    $record->files()->each(function (File $file) {
                        app(FileService::class)->removeFile($file);
                    });
                }),
                RestoreAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make()->before(function (Collection $records) {
                        /** @var Collection<int, Subfleet> $records */
                        $records->each(fn (Subfleet $record) => $record->files()->each(function (File $file) {
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

<?php

namespace App\Filament\Resources\Airports\Tables;

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
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use League\ISO3166\ISO3166;

class AirportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns(components: [
                TextColumn::make('icao')
                    ->label('ICAO')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('iata')
                    ->label('IATA')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name')
                    ->label(__('common.name'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('location')
                    ->label(__('user.location'))
                    ->searchable()
                    ->sortable(),

                IconColumn::make('hub')
                    ->label(__('airports.hub'))
                    ->boolean()
                    ->sortable(),

                TextInputColumn::make(name: 'ground_handling_cost')
                    ->label(__('airports.ground_handling_cost'))
                    ->toggleable(isToggledHiddenByDefault: true),

                TextInputColumn::make('fuel_jeta_cost')
                    ->label(__('airports.fuel_jeta_cost'))
                    ->toggleable(isToggledHiddenByDefault: true),

                TextInputColumn::make('fuel_100ll_cost')
                    ->label(__('airports.fuel_100ll_cost'))
                    ->toggleable(isToggledHiddenByDefault: true),

                TextInputColumn::make('fuel_mogas_cost')
                    ->label(__('airports.fuel_mogas_cost'))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('only_hubs')
                    ->label(__('airports.only_hubs'))
                    ->query(fn (Builder $query): Builder => $query->where('hub', 1)),

                SelectFilter::make('country')
                    ->label(label: __('common.country'))
                    ->options(collect((new ISO3166())->all())->mapWithKeys(fn (array $item, string $key) => [strtolower($item['alpha2']) => str_replace('&bnsp;', ' ', $item['name'])]))
                    ->searchable()
                    ->native(false),

                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                ForceDeleteAction::make()->before(callback: function (Airport $record) {
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
                        $records->each(fn (Airport $record) => $record->files()->each(function (File $file) {
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

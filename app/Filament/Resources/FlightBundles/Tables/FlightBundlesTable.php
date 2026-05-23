<?php

namespace App\Filament\Resources\FlightBundles\Tables;

use App\Models\FlightBundle;
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
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class FlightBundlesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->label(__('filament.bundles.fields.name'))
                    ->badge(),

                TextColumn::make('flights_count_display')
                    ->label(__('filament.bundles.fields.flights_count'))
                    ->state(fn (FlightBundle $record): string => (int) ($record->enabled_flights_count ?? 0).' / '.(int) ($record->disabled_flights_count ?? 0)),

                IconColumn::make('enabled')
                    ->boolean()
                    ->sortable()
                    ->label(__('filament.bundles.fields.enabled')),

                IconColumn::make('visible')
                    ->boolean()
                    ->sortable()
                    ->label(__('filament.bundles.fields.visible')),

                TextColumn::make('start_date')
                    ->date()
                    ->sortable()
                    ->toggleable()
                    ->label(__('common.start_date')),

                TextColumn::make('end_date')
                    ->date()
                    ->sortable()
                    ->toggleable()
                    ->label(__('common.end_date')),

                TextColumn::make('creator.name')
                    ->label(__('filament.bundles.fields.created_by'))
                    ->toggleable(),
            ])
            ->filters([
                TrashedFilter::make(),

                TernaryFilter::make('enabled'),

                TernaryFilter::make('visible'),
            ])
            ->recordActions([
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
            ])
            ->emptyStateActions([
            ]);
    }
}

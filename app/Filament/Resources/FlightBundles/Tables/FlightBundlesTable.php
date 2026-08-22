<?php

namespace App\Filament\Resources\FlightBundles\Tables;

use App\Enums\BundleType;
use App\Models\FlightBundle;
use Filament\Actions\ActionGroup;
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
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class FlightBundlesTable
{
    /**
     * `$forTours` is set by the tours resource, whose query is already narrowed
     * to `type = tour` — the type column and its filter would say the same
     * thing on every row, so they are dropped there.
     */
    public static function configure(Table $table, bool $forTours = false): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->label(__('filament.bundles.fields.name')),

                TextColumn::make('type')
                    ->label(__('filament.bundles.fields.type'))
                    ->badge()
                    ->color(fn (BundleType $state): string => $state === BundleType::Tour ? 'info' : 'gray')
                    ->sortable()
                    ->visible(!$forTours),

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

                // No option is "all types" — SelectFilter's own placeholder is
                // the unfiltered state, so there is nothing to add for it.
                SelectFilter::make('type')
                    ->label(__('filament.bundles.fields.type'))
                    ->options(BundleType::class)
                    ->visible(!$forTours),

                TernaryFilter::make('enabled'),

                TernaryFilter::make('visible'),
            ])
            ->recordActions([
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
            ])
            ->emptyStateActions([
            ]);
    }
}

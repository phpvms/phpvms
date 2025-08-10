<?php

namespace App\Filament\Resources\Fares\Tables;

use App\Models\Enums\FareType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class FaresTable
{
    public static function configure(Table $table): Table
    {

        return $table
            ->columns([
                TextColumn::make('code')
                    ->label(__('flights.code'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name')
                    ->label(__('common.name'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('type')
                    ->label(__('common.type'))
                    ->formatStateUsing(fn (int $state): string => FareType::label($state))
                    ->sortable(),

                TextColumn::make('price')
                    ->label(__('common.price'))
                    ->money(setting('units.currency'))
                    ->sortable(),

                TextColumn::make('cost')
                    ->label(__('common.cost'))
                    ->money(setting('units.currency'))
                    ->sortable(),

                TextColumn::make('notes')
                    ->label(__('common.notes')),

                IconColumn::make('active')
                    ->label(__('common.active'))
                    ->color(fn (bool $state): string => $state ? 'success' : 'danger')
                    ->icon(fn (bool $state): Heroicon => $state ? Heroicon::OutlinedCheckCircle : Heroicon::OutlinedXCircle)
                    ->sortable(),
            ])
            ->filters(filters: [
                SelectFilter::make('type')
                    ->label(__('common.type'))
                    ->options(FareType::labels()),

                TrashedFilter::make(),
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

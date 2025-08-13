<?php

namespace App\Filament\Resources\Ranks\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class RanksTable
{
    public static function configure(Table $table): Table
    {

        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('common.name'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('hours')
                    ->label(trans_choice('common.hour', 2))
                    ->sortable(),

                TextColumn::make('acars_pay')
                    ->label(__('common.acars_pay'))
                    ->toggleable()
                    ->sortable(),

                IconColumn::make('auto_approve_acars')
                    ->label(__('filament.rank_auto_approve_acars'))
                    ->toggleable()
                    ->boolean()
                    ->sortable(),

                TextColumn::make('manual_pay')
                    ->label(__('common.manual_pay'))
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),

                IconColumn::make('auto_approve_manual')
                    ->label(__('filament.rank_auto_approve_manual'))
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->boolean()
                    ->sortable(),

                IconColumn::make('auto_promote')
                    ->label(__('filament.rank_auto_promote'))
                    ->boolean()
                    ->sortable(),
            ])
            ->defaultSort('hours')
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
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

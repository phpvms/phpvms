<?php

namespace App\Filament\Resources\Expenses\Tables;

use App\Models\Enums\ExpenseType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ExpensesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('common.name'))
                    ->sortable()
                    ->searchable(),

                TextColumn::make('type')
                    ->label(__('common.type'))
                    ->sortable()
                    ->formatStateUsing(fn ($state) => ExpenseType::label($state)),

                TextColumn::make('amount')
                    ->label(__('common.amount'))
                    ->sortable()
                    ->money(setting('units.currency')),

                TextColumn::make('airline.name')
                    ->label(__('common.airline'))
                    ->sortable()
                    ->searchable(),

                IconColumn::make('active')
                    ->label(__('common.active'))
                    ->color(fn (bool $state): string => $state ? 'success' : 'danger')
                    ->icon(fn (bool $state): Heroicon => $state ? Heroicon::OutlinedCheckCircle : Heroicon::OutlinedXCircle)
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label(__('common.type'))
                    ->options(ExpenseType::labels())
                    ->multiple(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateActions([
            ]);
    }
}

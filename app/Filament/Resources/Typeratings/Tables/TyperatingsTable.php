<?php

namespace App\Filament\Resources\Typeratings\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TyperatingsTable
{
    public static function configure(Table $table): Table
    {

        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('common.name'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('type')
                    ->label(__('common.type'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('description')
                    ->label(__('common.description')),

                TextColumn::make('image_url')
                    ->label(__('common.image_url')),

                IconColumn::make('active')
                    ->label(__('common.active'))
                    ->color(fn (bool $state): string => $state ? 'success' : 'danger')
                    ->icon(fn (bool $state): Heroicon => $state ? Heroicon::OutlinedCheckCircle : Heroicon::OutlinedXCircle)
                    ->sortable(),
            ])
            ->filters([
                //
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
                CreateAction::make()
                    ->icon(Heroicon::OutlinedPlusCircle),
            ]);
    }
}

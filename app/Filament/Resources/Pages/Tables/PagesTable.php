<?php

namespace App\Filament\Resources\Pages\Tables;

use App\Models\Enums\PageType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PagesTable
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
                    ->formatStateUsing(fn (int $state): string => PageType::label($state)),

                IconColumn::make('public')
                    ->label(__('common.public'))
                    ->boolean()
                    ->sortable(),

                IconColumn::make('enabled')
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label(__('common.type'))
                    ->options(PageType::labels()),
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

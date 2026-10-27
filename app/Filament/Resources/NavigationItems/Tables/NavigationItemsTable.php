<?php

declare(strict_types=1);

namespace App\Filament\Resources\NavigationItems\Tables;

use App\Enums\NavigationTarget;
use App\Models\NavigationItem;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class NavigationItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // The menu has one order and it is this one, so drag-to-reorder
            // writes straight to the column the front end reads.
            ->reorderable('sort')
            ->defaultSort('sort')
            ->columns([
                TextColumn::make('name')
                    ->label(__('filament.navigation_items.name'))
                    ->searchable(),

                TextColumn::make('label')
                    ->label(__('filament.navigation_items.label'))
                    ->searchable(),

                TextColumn::make('parent.name')
                    ->label(__('filament.navigation_items.parent'))
                    ->placeholder(__('filament.navigation_items.parent_none')),

                TextColumn::make('target_type')
                    ->label(__('filament.navigation_items.target_type'))
                    ->badge(),

                TextColumn::make('target_value')
                    ->label(__('common.link'))
                    ->limit(40)
                    ->state(fn (NavigationItem $record): ?string => $record->resolveUrl()),

                ToggleColumn::make('visible')
                    ->label(__('filament.navigation_items.visible')),

                IconColumn::make('public')
                    ->label(__('common.public'))
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('target_type')
                    ->label(__('filament.navigation_items.target_type'))
                    ->options(NavigationTarget::class),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

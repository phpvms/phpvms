<?php

namespace App\Filament\Resources\Pages\Tables;

use App\Enums\PageStatus;
use App\Enums\PageType;
use App\Models\Page;
use Filament\Actions\ActionGroup;
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

                TextColumn::make('path')
                    ->label(__('filament.system_pages.path'))
                    ->sortable()
                    ->searchable()
                    ->placeholder('—'),

                TextColumn::make('type')
                    ->label(__('common.type'))
                    ->sortable()
                    ->badge(),

                TextColumn::make('status')
                    ->label(__('common.status'))
                    ->sortable()
                    ->badge()
                    // Flags a draft that differs from what is live, which is the
                    // one state an author can otherwise lose track of.
                    ->description(fn (Page $record): ?string => $record->status === PageStatus::PUBLISHED
                        && $record->draft_body !== $record->body
                            ? __('filament.system_pages.unpublished_changes')
                            : null),

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
                    ->options(PageType::class),

                SelectFilter::make('status')
                    ->label(__('common.status'))
                    ->options(PageStatus::class),
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
            ])
            ->emptyStateActions([
            ]);
    }
}

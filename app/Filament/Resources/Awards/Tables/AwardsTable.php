<?php

namespace App\Filament\Resources\Awards\Tables;

use App\Models\Award;
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
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AwardsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // The `type` column asks every row whether it is rules-based, which
            // is a question about its AwardRule — one query per row without this.
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('rule'))
            ->columns([
                TextColumn::make('name')
                    ->label(__('common.name'))
                    ->sortable()
                    ->searchable(),

                TextColumn::make('description')
                    ->label(__('common.description'))
                    ->html(),

                TextColumn::make('category')
                    ->label(__('common.category'))
                    ->badge()
                    ->color('gray')
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('type')
                    ->label(__('filament.award_type'))
                    ->state(fn (Award $record): string => $record->isRulesBased() ? __('filament.award_type_rules') : __('filament.award_type_legacy'))
                    ->badge()
                    ->color(fn (Award $record): string => $record->isRulesBased() ? 'success' : 'gray'),

                TextColumn::make('trigger')
                    ->label(__('filament.award_trigger'))
                    ->placeholder('—'),

                IconColumn::make('active')
                    ->label(__('common.active'))
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->label(__('common.category'))
                    ->options(fn (): array => Award::categoryOptions()),

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

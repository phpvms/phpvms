<?php

namespace App\Filament\Resources\UserFields\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UserFieldsTable
{
    public static function configure(Table $table): Table
    {

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->where('internal', false))
            ->columns([
                TextColumn::make('name')
                    ->label(__('common.name'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('description')
                    ->label(__('common.description')),

                IconColumn::make('required')
                    ->label(__('common.required'))
                    ->boolean()
                    ->sortable(),

                IconColumn::make('show_on_registration')
                    ->label(__('filament.user_field_show_on_registration'))
                    ->boolean()
                    ->sortable(),

                IconColumn::make('private')
                    ->label(__('filament.user_field_private'))
                    ->boolean()
                    ->sortable(),

                IconColumn::make('active')
                    ->label(__('common.active'))
                    ->boolean()
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
            ]);
    }
}

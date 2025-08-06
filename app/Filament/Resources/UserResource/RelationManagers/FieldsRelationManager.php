<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FieldsRelationManager extends RelationManager
{
    protected static string $relationship = 'fields';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('value')->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->modifyQueryUsing(fn (Builder $query) => $query->whereHas('field', fn (Builder $subQuery) => $subQuery->where('internal', false)))
            ->columns([
                TextColumn::make('name'),
                TextInputColumn::make('value'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
            ])
            ->recordActions([
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

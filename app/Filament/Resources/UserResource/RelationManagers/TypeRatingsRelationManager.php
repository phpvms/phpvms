<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class TypeRatingsRelationManager extends RelationManager
{
    protected static string $relationship = 'typeratings';

    public function form(Form $form): Form
    {
        $typeRatingRepo = app(\App\Repositories\TypeRatingRepository::class);
        return $form
            ->schema([
                Forms\Components\Select::make('typerating_id')->searchable()->options($typeRatingRepo->all()->pluck('name', 'id')->toArray()),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name'),
                Tables\Columns\TextColumn::make('type'),
                Tables\Columns\TextColumn::make('description'),
                Tables\Columns\TextColumn::make('image_url'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\AttachAction::make()->label('Add'),
            ])
            ->actions([
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateActions([
            ]);
    }
}

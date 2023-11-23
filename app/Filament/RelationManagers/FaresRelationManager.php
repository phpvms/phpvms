<?php

namespace App\Filament\RelationManagers;

use App\Models\Fare;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class FaresRelationManager extends RelationManager
{
    protected static string $relationship = 'fares';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public function table(Table $table): Table
    {
        // Edit pivot is working well but pivot value aren't displayed in table, need to be reworked. It displays values from the relationship and not the pivot
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')->formatStateUsing(fn (Fare $record): string => $record->name.' ('.$record->code.')'),
                Tables\Columns\TextInputColumn::make('capacity'),
                Tables\Columns\TextInputColumn::make('price'),
                Tables\Columns\TextInputColumn::make('cost'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\AttachAction::make(),
            ])
            ->actions([
                Tables\Actions\DetachAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DetachBulkAction::make(),
                ]),
            ]);
    }
}

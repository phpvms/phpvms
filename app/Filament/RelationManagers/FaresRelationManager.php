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
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')->formatStateUsing(fn (Fare $record): string => $record->name.' ('.$record->code.')'),
                Tables\Columns\TextInputColumn::make('pivot.capacity')
                    ->label('Capacity')
                    ->updateStateUsing(function (Fare $record, string $state) {
                        $record->pivot->update(['capacity' => $state]);
                    }),
                Tables\Columns\TextInputColumn::make('pivot.price')
                    ->label('Price')
                    ->updateStateUsing(function (Fare $record, string $state) {
                        $record->pivot->update(['price' => $state]);
                    }),
                Tables\Columns\TextInputColumn::make('pivot.cost')
                    ->label('Cost')
                    ->updateStateUsing(function (Fare $record, string $state) {
                        $record->pivot->update(['cost' => $state]);
                    }),
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

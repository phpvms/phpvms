<?php

namespace App\Filament\Resources\SubfleetResource\RelationManagers;

use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class RanksRelationManager extends RelationManager
{
    protected static string $relationship = 'ranks';

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
                Tables\Columns\TextColumn::make('name'),

                Tables\Columns\TextColumn::make('base_pay_rate'),

                Tables\Columns\TextInputColumn::make('acars_pay')
                    ->placeholder('Inherited'),

                Tables\Columns\TextInputColumn::make('manual_pay')
                    ->placeholder('Inherited'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\AttachAction::make()->icon('heroicon-o-plus-circle'),
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

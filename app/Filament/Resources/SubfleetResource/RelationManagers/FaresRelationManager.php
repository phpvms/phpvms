<?php

namespace App\Filament\Resources\SubfleetResource\RelationManagers;

use App\Models\Enums\FareType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

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
                Tables\Columns\TextColumn::make('name'),
                Tables\Columns\TextColumn::make('code'),
                Tables\Columns\TextColumn::make('type')->formatStateUsing(fn (string $state): string => FareType::label($state)),
                // Some issues with pivot names
                Tables\Columns\TextInputColumn::make('capacity'),
                Tables\Columns\TextInputColumn::make('price'),
                Tables\Columns\TextInputColumn::make('cost'),
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

<?php

namespace App\Filament\Resources\PirepResource\RelationManagers;

use App\Models\PirepFare;
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
            ->recordTitleAttribute('code')
            ->columns([
                Tables\Columns\TextColumn::make('fare')->formatStateUsing(fn (PirepFare $record): string => $record->name.' ('.$record->code.')'),
                Tables\Columns\TextInputColumn::make('count')->disabled(fn (PirepFare $record): bool => $record->pirep->read_only)->step(0.01)->rules(['min:0']),
                Tables\Columns\TextInputColumn::make('price')->disabled(fn (PirepFare $record): bool => $record->pirep->read_only)->step(0.01)->rules(['min:0']),
                Tables\Columns\TextColumn::make('capacity'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                //
            ])
            ->actions([
                //
            ])
            ->bulkActions([
                //
            ]);
    }
}

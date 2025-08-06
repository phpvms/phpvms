<?php

namespace App\Filament\Resources\PirepResource\RelationManagers;

use App\Models\PirepFare;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Table;

class FaresRelationManager extends RelationManager
{
    protected static string $relationship = 'fares';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                //
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('code')
            ->columns([
                TextColumn::make('fare')->formatStateUsing(fn (PirepFare $record): string => $record->name.' ('.$record->code.')'),
                TextInputColumn::make('count')->disabled(fn (PirepFare $record): bool => $record->pirep->read_only)->step(0.01)->rules(['min:0']),
                TextInputColumn::make('price')->disabled(fn (PirepFare $record): bool => $record->pirep->read_only)->step(0.01)->rules(['min:0']),
                TextColumn::make('capacity'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                //
            ])
            ->recordActions([
                //
            ])
            ->toolbarActions([
                //
            ]);
    }
}

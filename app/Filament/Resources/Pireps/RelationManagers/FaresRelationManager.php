<?php

namespace App\Filament\Resources\Pireps\RelationManagers;

use App\Models\PirepFare;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

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
                TextColumn::make('fare')
                    ->label(trans_choice('pireps.fare', 1))
                    ->formatStateUsing(fn (PirepFare $record): string => $record->name.' ('.$record->code.')'),

                TextInputColumn::make('count')
                    ->label(__('pireps.count'))
                    ->disabled(fn (PirepFare $record): bool => $record->pirep->read_only)
                    ->step(0.01)
                    ->rules(['min:0']),

                TextInputColumn::make('price')
                    ->label(__('common.price'))
                    ->disabled(fn (PirepFare $record): bool => $record->pirep->read_only)
                    ->step(0.01)
                    ->rules(['min:0']),

                TextColumn::make('capacity')
                    ->label(__('common.capacity')),
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

    public static function getModelLabel(): string
    {
        return trans_choice( 'pireps.fare', 1);
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return trans_choice('pireps.fare', 2);
    }
}

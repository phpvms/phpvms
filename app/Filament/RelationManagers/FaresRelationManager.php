<?php

namespace App\Filament\RelationManagers;

use App\Models\Fare;
use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
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
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')->formatStateUsing(fn (Fare $record): string => $record->name.' ('.$record->code.')'),
                TextInputColumn::make('pivot.capacity')
                    ->placeholder('Inherited')
                    ->label('Capacity')
                    ->updateStateUsing(function (Fare $record, string $state) {
                        $record->pivot->update(['capacity' => $state]);
                    }),
                TextInputColumn::make('pivot.price')
                    ->label('Price')
                    ->placeholder('Inherited')
                    ->updateStateUsing(function (Fare $record, string $state) {
                        $record->pivot->update(['price' => $state]);
                    }),
                TextInputColumn::make('pivot.cost')
                    ->label('Cost')
                    ->placeholder('Inherited')
                    ->updateStateUsing(function (Fare $record, string $state) {
                        $record->pivot->update(['cost' => $state]);
                    }),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                AttachAction::make(),
            ])
            ->recordActions([
                DetachAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make(),
                ]),
            ]);
    }
}

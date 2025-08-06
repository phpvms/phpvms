<?php

namespace App\Filament\Resources\TypeRatingResource\RelationManagers;

use App\Models\Subfleet;
use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SubfleetsRelationManager extends RelationManager
{
    protected static string $relationship = 'subfleets';

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
                TextColumn::make('airline.name')->label('Airline'),
                TextColumn::make('name')->label('Name'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                AttachAction::make()
                    ->icon('heroicon-o-plus-circle')
                    ->multiple()
                    ->preloadRecordSelect()
                    ->recordTitle(fn (Subfleet $record): string => $record->airline->name.' - '.$record->name),
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

<?php

namespace App\Filament\Resources\RankResource\RelationManagers;

use App\Models\Subfleet;
use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Table;

class SubfleetsRelationManager extends RelationManager
{
    protected static string $relationship = 'subfleets';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([

            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('airline.name')->label('Airline'),
                TextColumn::make('name'),
                TextInputColumn::make('acars_pay')->placeholder('inherited')->rules(['nullable', 'numeric', 'min:0']),
                TextInputColumn::make('manual_pay')->placeholder('inherited')->rules(['nullable', 'numeric', 'min:0']),
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

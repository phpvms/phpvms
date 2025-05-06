<?php

namespace App\Filament\Resources\RankResource\RelationManagers;

use App\Models\Subfleet;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class SubfleetsRelationManager extends RelationManager
{
    protected static string $relationship = 'subfleets';

    public function form(Form $form): Form
    {
        return $form
            ->schema([

            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('airline.name')->label('Airline'),
                Tables\Columns\TextColumn::make('name'),
                Tables\Columns\TextInputColumn::make('acars_pay')->placeholder('inherited')->rules(['nullable', 'numeric', 'min:0']),
                Tables\Columns\TextInputColumn::make('manual_pay')->placeholder('inherited')->rules(['nullable', 'numeric', 'min:0']),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\AttachAction::make()
                    ->icon('heroicon-o-plus-circle')
                    ->multiple()
                    ->preloadRecordSelect()
                    ->recordTitle(fn (Subfleet $record): string => $record->airline->name.' - '.$record->name),
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

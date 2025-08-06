<?php

namespace App\Filament\Resources\SubfleetResource\RelationManagers;

use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Table;

class RanksRelationManager extends RelationManager
{
    protected static string $relationship = 'ranks';

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
                TextColumn::make('name'),

                TextColumn::make('base_pay_rate'),

                TextInputColumn::make('acars_pay')
                    ->placeholder('Inherited'),

                TextInputColumn::make('manual_pay')
                    ->placeholder('Inherited'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                AttachAction::make()->icon('heroicon-o-plus-circle')->recordSelect(fn (Select $select) => $select->multiple()),
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

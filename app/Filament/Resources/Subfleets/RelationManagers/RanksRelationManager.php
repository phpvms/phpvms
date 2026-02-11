<?php

namespace App\Filament\Resources\Subfleets\RelationManagers;

use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

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
                TextColumn::make('name')
                    ->label(__('common.name')),

                TextInputColumn::make('acars_pay')
                    ->label(__('filament.rank_acars_base_pay_rate'))
                    ->placeholder(__('common.inherited')),

                TextInputColumn::make('manual_pay')
                    ->label(__('filament.rank_manual_base_pay_rate'))
                    ->placeholder(__('common.inherited')),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                AttachAction::make()
                    ->preloadRecordSelect()
                    ->multiple(),
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

    public static function getModelLabel(): string
    {
        return __('common.rank');
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return str(__('common.rank'))
            ->plural()
            ->toString();
    }
}

<?php

namespace App\Filament\Resources\Subfleets\RelationManagers;

use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class TyperatingsRelationManager extends RelationManager
{
    protected static string $relationship = 'typeratings';

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
                TextColumn::make('type')
                    ->label(__('common.type')),

                TextColumn::make('name')
                    ->label(__('common.name')),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                AttachAction::make()
                    ->multiple()
                    ->preloadRecordSelect(),
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
        return __('common.typerating');
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return str(__('common.typerating'))
            ->plural()
            ->toString();
    }
}

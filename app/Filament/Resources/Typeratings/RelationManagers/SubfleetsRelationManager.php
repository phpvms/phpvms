<?php

namespace App\Filament\Resources\Typeratings\RelationManagers;

use App\Models\Subfleet;
use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

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
                TextColumn::make('airline.name')
                    ->label(__('common.airline')),

                TextColumn::make('name')
                    ->label(__('common.name')),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                AttachAction::make()
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

    public static function getModelLabel(): string
    {
        return __('common.subfleet');
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return str(__('common.subfleet'))
            ->plural()
            ->toString();
    }
}

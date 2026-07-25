<?php

namespace App\Filament\Resources\FlightBundles\Resources\Flight\RelationManagers;

use App\Models\Subfleet;
use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Override;

class SubfleetsRelationManager extends RelationManager
{
    protected static string $relationship = 'subfleets';

    #[Override]
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
            // Filament's guess (`flights`) happens to be right today; naming it
            // pins the attach modal against a rename on Subfleet.
            ->inverseRelationship('flights')
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('airline.name')
                    ->label(__('common.airline')),

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
                    ->preloadRecordSelect()
                    // recordTitle() reads the airline off every option.
                    ->recordSelectOptionsQuery(fn (Builder $query): Builder => $query->with('airline'))
                    ->recordTitle(fn (Subfleet $record): string => trim(($record->airline?->name ?? '').' - '.$record->name, ' -')),
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

    #[Override]
    protected static function getModelLabel(): string
    {
        return __('common.subfleet');
    }

    #[Override]
    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return str(__('common.subfleet'))
            ->plural()
            ->toString();
    }
}

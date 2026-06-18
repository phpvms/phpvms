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
use Illuminate\Database\Eloquent\Model;
use Override;

class FaresRelationManager extends RelationManager
{
    protected static string $relationship = 'fares';

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
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label(__('common.name'))
                    ->formatStateUsing(fn (Fare $record): string => $record->name.' ('.$record->code.')'),

                TextInputColumn::make('pivot.capacity')
                    ->placeholder(__('common.inherited'))
                    ->label(__('common.capacity'))
                    ->updateStateUsing(function (Fare $record, string $state): void {
                        $record->pivot->update(['capacity' => $state]);
                    }),
                // The static price is only meaningful when auto pricing is off
                // (auto pricing computes the price), so hide it by default when
                // auto pricing is on — the inverse of the override columns below.
                TextInputColumn::make('pivot.price')
                    ->label(__('common.price'))
                    ->placeholder(__('common.inherited'))
                    ->toggleable(isToggledHiddenByDefault: (bool) setting('fares.auto_price', false))
                    ->updateStateUsing(function (Fare $record, string $state): void {
                        $record->pivot->update(['price' => $state]);
                    }),
                TextInputColumn::make('pivot.cost')
                    ->label(__('common.cost'))
                    ->placeholder(__('common.inherited'))
                    ->updateStateUsing(function (Fare $record, string $state): void {
                        $record->pivot->update(['cost' => $state]);
                    }),
                // Auto-price override columns. Toggleable and hidden by default
                // unless auto pricing is enabled, to keep the table uncluttered
                // when these values aren't in use.
                TextInputColumn::make('pivot.base_price')
                    ->label(__('filament.fare_base_price'))
                    ->placeholder(__('common.inherited'))
                    ->toggleable(isToggledHiddenByDefault: !(bool) setting('fares.auto_price', false))
                    ->updateStateUsing(function (Fare $record, string $state): void {
                        $record->pivot->update(['base_price' => $state]);
                    }),
                TextInputColumn::make('pivot.per_nm')
                    ->label(__('filament.fare_per_nm'))
                    ->placeholder(__('common.inherited'))
                    ->toggleable(isToggledHiddenByDefault: !(bool) setting('fares.auto_price', false))
                    ->updateStateUsing(function (Fare $record, string $state): void {
                        $record->pivot->update(['per_nm' => $state]);
                    }),
                TextInputColumn::make('pivot.multiplier')
                    ->label(__('filament.fare_multiplier'))
                    ->placeholder(__('common.inherited'))
                    ->toggleable(isToggledHiddenByDefault: !(bool) setting('fares.auto_price', false))
                    ->updateStateUsing(function (Fare $record, string $state): void {
                        $record->pivot->update(['multiplier' => $state]);
                    }),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                AttachAction::make()
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

    #[Override]
    protected static function getModelLabel(): string
    {
        return trans_choice( 'pireps.fare', 1);
    }

    #[Override]
    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return trans_choice('pireps.fare', 2);
    }
}

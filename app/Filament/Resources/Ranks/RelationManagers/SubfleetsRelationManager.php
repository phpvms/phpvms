<?php

namespace App\Filament\Resources\Ranks\RelationManagers;

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

            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            // Filament's guess (`ranks`) happens to be right today; naming it
            // pins the attach modal against a rename on Subfleet.
            ->inverseRelationship('ranks')
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('airline.name')
                    ->label(__('common.airline')),

                TextColumn::make('name')
                    ->label(__('common.name')),

                TextInputColumn::make('acars_pay')
                    ->label(__('common.acars_pay'))
                    ->placeholder(__('common.inherited'))
                    ->rules(['nullable', 'numeric', 'min:0']),

                TextInputColumn::make('manual_pay')
                    ->label(__('common.manual_pay'))
                    ->placeholder(__('common.inherited'))
                    ->rules(['nullable', 'numeric', 'min:0']),
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

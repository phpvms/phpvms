<?php

namespace App\Filament\Resources\Users\RelationManagers;

use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Override;

class TypeRatingsRelationManager extends RelationManager
{
    protected static string $relationship = 'typeratings';

    #[Override]
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                //
            ]);
    }

    /**
     * Its own table rather than the shared `TyperatingsTable`: that one belongs
     * to the standalone resource, so its edit and delete actions operate on the
     * `Typerating` record itself. On a user's page the only meaningful
     * operations are attaching and detaching the pivot row.
     */
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
                AttachAction::make(),
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
        return __('common.typerating');
    }

    #[Override]
    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return str(__('common.typerating'))
            ->plural()
            ->toString();
    }
}

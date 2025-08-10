<?php

namespace App\Filament\Resources\Users\RelationManagers;

use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class AwardsRelationManager extends RelationManager
{
    protected static string $relationship = 'awards';

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

                TextColumn::make('description')
                    ->label(__('common.description')),

                ImageColumn::make('image')
                    ->label(__('common.image'))
                    ->url(fn ($record) => $record->image_url),
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
            ])
            ->emptyStateActions([
            ]);
    }

    public static function getModelLabel(): string
    {
        return __('common.award');
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return str(__('common.award'))
            ->plural()
            ->toString();
    }
}

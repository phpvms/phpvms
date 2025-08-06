<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use App\Repositories\TypeRatingRepository;
use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TypeRatingsRelationManager extends RelationManager
{
    protected static string $relationship = 'typeratings';

    public function form(Schema $schema): Schema
    {
        $typeRatingRepo = app(TypeRatingRepository::class);

        return $schema
            ->components([
                Select::make('typerating_id')->searchable()->options($typeRatingRepo->all()->pluck('name', 'id')->toArray()),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name'),
                TextColumn::make('type'),
                TextColumn::make('description'),
                TextColumn::make('image_url'),
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
}

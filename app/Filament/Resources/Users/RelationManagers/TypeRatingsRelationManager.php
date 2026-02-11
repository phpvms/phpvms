<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Filament\Resources\Typeratings\Tables\TyperatingsTable;
use App\Repositories\TypeRatingRepository;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class TypeRatingsRelationManager extends RelationManager
{
    protected static string $relationship = 'typeratings';

    public function form(Schema $schema): Schema
    {
        $typeRatingRepo = app(TypeRatingRepository::class);

        return $schema
            ->components([
                Select::make('typerating_id')
                    ->label(__('common.typerating'))
                    ->searchable()
                    ->options($typeRatingRepo->all()->pluck('name', 'id')->toArray()),
            ]);
    }

    public function table(Table $table): Table
    {
        return TyperatingsTable::configure($table);
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

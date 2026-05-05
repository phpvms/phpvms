<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Filament\Resources\Typeratings\Tables\TyperatingsTable;
use App\Models\Typerating;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class TypeRatingsRelationManager extends RelationManager
{
    protected static string $relationship = 'typeratings';

    #[\Override]
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('typerating_id')
                    ->label(__('common.typerating'))
                    ->searchable()
                    ->options(Typerating::pluck('name', 'id')->toArray()),
            ]);
    }

    public function table(Table $table): Table
    {
        return TyperatingsTable::configure($table);
    }

    #[\Override]
    protected static function getModelLabel(): string
    {
        return __('common.typerating');
    }

    #[\Override]
    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return str(__('common.typerating'))
            ->plural()
            ->toString();
    }
}

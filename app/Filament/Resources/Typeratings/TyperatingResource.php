<?php

namespace App\Filament\Resources\Typeratings;

use App\Filament\Resources\Typeratings\Pages\CreateTyperating;
use App\Filament\Resources\Typeratings\Pages\EditTyperating;
use App\Filament\Resources\Typeratings\Pages\ListTyperating;
use App\Filament\Resources\Typeratings\RelationManagers\SubfleetsRelationManager;
use App\Filament\Resources\Typeratings\Schemas\TyperatingForm;
use App\Filament\Resources\Typeratings\Tables\TyperatingsTable;
use App\Models\Enums\NavigationGroup;
use App\Models\Typerating;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TyperatingResource extends Resource
{
    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Config;

    protected static ?int $navigationSort = 5;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedRocketLaunch;

    protected static ?string $model = Typerating::class;

    protected static ?string $recordTitleAttribute = 'name';

    #[\Override]
    public static function form(Schema $schema): Schema
    {
        return TyperatingForm::configure($schema);
    }

    #[\Override]
    public static function table(Table $table): Table
    {
        return TyperatingsTable::configure($table);
    }

    #[\Override]
    public static function getRelations(): array
    {
        return [
            SubfleetsRelationManager::class,
        ];
    }

    #[\Override]
    public static function getPages(): array
    {
        return [
            'index'  => ListTyperating::route('/'),
            'create' => CreateTyperating::route('/create'),
            'edit'   => EditTyperating::route('/{record}/edit'),
        ];
    }

    #[\Override]
    public static function getModelLabel(): string
    {
        return __('common.typerating');
    }
}

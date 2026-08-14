<?php

namespace App\Filament\Resources\Airlines;

use App\Enums\NavigationGroup;
use App\Filament\RelationManagers\FilesRelationManager;
use App\Filament\Resources\Airlines\Pages\CreateAirline;
use App\Filament\Resources\Airlines\Pages\EditAirline;
use App\Filament\Resources\Airlines\Pages\ListAirlines;
use App\Filament\Resources\Airlines\Schemas\AirlineForm;
use App\Filament\Resources\Airlines\Tables\AirlinesTable;
use App\Models\Airline;
use BackedEnum;
use Daljo25\FilamentTablerIcons\Enums\TablerIcon;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Override;
use UnitEnum;

class AirlineResource extends Resource
{
    protected static ?string $model = Airline::class;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Planning;

    protected static ?int $navigationSort = 4;

    protected static string|BackedEnum|null $navigationIcon = TablerIcon::Building;

    protected static ?string $recordTitleAttribute = 'name';

    #[Override]
    public static function form(Schema $schema): Schema
    {
        return AirlineForm::configure($schema);
    }

    #[Override]
    public static function table(Table $table): Table
    {
        return AirlinesTable::configure($table);
    }

    #[Override]
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    #[Override]
    public static function getRelations(): array
    {
        return [
            FilesRelationManager::class,
        ];
    }

    #[Override]
    public static function getPages(): array
    {
        return [
            'index'  => ListAirlines::route('/'),
            'create' => CreateAirline::route('/create'),
            'edit'   => EditAirline::route('/{record}/edit'),
        ];
    }

    #[Override]
    public static function getModelLabel(): string
    {
        return __('common.airline');
    }
}

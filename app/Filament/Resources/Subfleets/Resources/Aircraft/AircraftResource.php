<?php

namespace App\Filament\Resources\Subfleets\Resources\Aircraft;

use App\Filament\RelationManagers\ExpensesRelationManager;
use App\Filament\RelationManagers\FilesRelationManager;
use App\Filament\Resources\Subfleets\Resources\Aircraft\Pages\CreateAircraft;
use App\Filament\Resources\Subfleets\Resources\Aircraft\Pages\EditAircraft;
use App\Filament\Resources\Subfleets\Resources\Aircraft\Schemas\AircraftForm;
use App\Filament\Resources\Subfleets\Resources\Aircraft\Tables\AircraftTable;
use App\Filament\Resources\Subfleets\SubfleetResource;
use App\Models\Aircraft;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class AircraftResource extends Resource
{
    protected static ?string $model = Aircraft::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $parentResource = SubfleetResource::class;

    protected static ?string $recordTitleAttribute = 'registration';

    public static function form(Schema $schema): Schema
    {
        return AircraftForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AircraftTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ExpensesRelationManager::class,
            FilesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'create' => CreateAircraft::route('/create'),
            'edit'   => EditAircraft::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'registration', 'icao'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string|Htmlable
    {
        return $record->name.' - '.$record->registration;
    }
}

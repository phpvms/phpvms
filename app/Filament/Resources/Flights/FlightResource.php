<?php

namespace App\Filament\Resources\Flights;

use App\Filament\RelationManagers\FaresRelationManager;
use App\Filament\Resources\Flights\Pages\CreateFlight;
use App\Filament\Resources\Flights\Pages\EditFlight;
use App\Filament\Resources\Flights\Pages\ListFlights;
use App\Filament\Resources\Flights\RelationManagers\FieldValuesRelationManager;
use App\Filament\Resources\Flights\RelationManagers\SubfleetsRelationManager;
use App\Filament\Resources\Flights\Schemas\FlightForm;
use App\Filament\Resources\Flights\Tables\FlightsTable;
use App\Models\Enums\NavigationGroup;
use App\Models\Flight;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class FlightResource extends Resource
{
    protected static ?string $model = Flight::class;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Operations;

    protected static ?int $navigationSort = 2;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsVertical;

    public static function form(Schema $schema): Schema
    {
        return FlightForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FlightsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            SubfleetsRelationManager::class,
            FieldValuesRelationManager::class,
            FaresRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListFlights::route('/'),
            'create' => CreateFlight::route('/create'),
            'edit'   => EditFlight::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['flight_number', 'route_code'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string|Htmlable
    {
        return $record->airline->icao.$record->flight_number;
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            __('airports.departure') => $record->dpt_airport_id,
            __('airports.arrival')   => $record->arr_airport_id,
        ];
    }

    public static function getModelLabel(): string
    {
        return trans_choice('common.flight', 1);
    }
}

<?php

namespace App\Filament\Resources\FlightBundles\Resources\Flight;

use App\Filament\RelationManagers\FaresRelationManager;
use App\Filament\Resources\FlightBundles\FlightBundleResource;
use App\Filament\Resources\FlightBundles\Resources\Flight\Pages\CreateFlight;
use App\Filament\Resources\FlightBundles\Resources\Flight\Pages\EditFlight;
use App\Filament\Resources\FlightBundles\Resources\Flight\RelationManagers\FieldValuesRelationManager;
use App\Filament\Resources\FlightBundles\Resources\Flight\RelationManagers\SubfleetsRelationManager;
use App\Filament\Resources\FlightBundles\Resources\Flight\Schemas\FlightForm;
use App\Filament\Resources\FlightBundles\Resources\Flight\Tables\FlightsTable;
use App\Models\Flight;
use Filament\Resources\ParentResourceRegistration;
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

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    protected static ?string $parentResource = FlightBundleResource::class;

    #[\Override]
    public static function getParentResourceRegistration(): ?ParentResourceRegistration
    {
        return parent::getParentResourceRegistration()?->inverseRelationship('bundle');
    }

    protected static ?string $slug = 'flight';

    protected static ?string $recordTitleAttribute = 'ident';

    #[\Override]
    public static function form(Schema $schema): Schema
    {
        return FlightForm::configure($schema);
    }

    #[\Override]
    public static function table(Table $table): Table
    {
        return FlightsTable::configure($table);
    }

    #[\Override]
    public static function getRelations(): array
    {
        return [
            SubfleetsRelationManager::class,
            FieldValuesRelationManager::class,
            FaresRelationManager::class,
        ];
    }

    #[\Override]
    public static function getPages(): array
    {
        return [
            'create' => CreateFlight::route('/create'),
            'edit'   => EditFlight::route('/{record}/edit'),
        ];
    }

    #[\Override]
    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    #[\Override]
    public static function getGloballySearchableAttributes(): array
    {
        return ['flight_number', 'route_code'];
    }

    /**
     * @param Flight $record
     */
    #[\Override]
    public static function getGlobalSearchResultTitle(Model $record): string|Htmlable
    {
        return $record->airline->icao.$record->flight_number;
    }

    /**
     * @param Flight $record
     */
    #[\Override]
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            __('airports.departure') => $record->dpt_airport_id,
            __('airports.arrival')   => $record->arr_airport_id,
        ];
    }

    #[\Override]
    public static function getModelLabel(): string
    {
        return trans_choice('common.flight', 1);
    }
}

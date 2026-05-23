<?php

namespace App\Filament\Resources\FlightBundles;

use App\Enums\NavigationGroup;
use App\Filament\Resources\FlightBundles\Pages\CreateFlightBundle;
use App\Filament\Resources\FlightBundles\Pages\EditFlightBundle;
use App\Filament\Resources\FlightBundles\Pages\ListFlightBundles;
use App\Filament\Resources\FlightBundles\RelationManagers\FlightsRelationManager;
use App\Filament\Resources\FlightBundles\Schemas\FlightBundleForm;
use App\Filament\Resources\FlightBundles\Tables\FlightBundlesTable;
use App\Models\FlightBundle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class FlightBundleResource extends Resource
{
    protected static ?string $model = FlightBundle::class;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Operations;

    protected static ?int $navigationSort = 2;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    protected static ?string $slug = 'flights';

    #[\Override]
    public static function form(Schema $schema): Schema
    {
        return FlightBundleForm::configure($schema);
    }

    #[\Override]
    public static function table(Table $table): Table
    {
        return FlightBundlesTable::configure($table);
    }

    #[\Override]
    public static function getRelations(): array
    {
        return [
            FlightsRelationManager::class,
        ];
    }

    #[\Override]
    public static function getPages(): array
    {
        return [
            'index'  => ListFlightBundles::route('/'),
            'create' => CreateFlightBundle::route('/create'),
            'edit'   => EditFlightBundle::route('/{record}/edit'),
        ];
    }

    #[\Override]
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ])
            ->withCount([
                'flights as enabled_flights_count'  => fn (Builder $q) => $q->where('enabled', true),
                'flights as disabled_flights_count' => fn (Builder $q) => $q->where('enabled', false),
            ]);
    }

    #[\Override]
    public static function getGloballySearchableAttributes(): array
    {
        return ['name'];
    }

    /**
     * @param FlightBundle $record
     */
    #[\Override]
    public static function getGlobalSearchResultTitle(Model $record): string|Htmlable
    {
        return $record->name;
    }

    #[\Override]
    public static function getModelLabel(): string
    {
        return __('filament.bundles.label');
    }

    #[\Override]
    public static function getNavigationLabel(): string
    {
        return __('filament.flights.navigation_label');
    }
}

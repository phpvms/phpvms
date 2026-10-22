<?php

declare(strict_types=1);

namespace App\Filament\Resources\Tours;

use App\Enums\BundleType;
use App\Filament\Resources\FlightBundles\FlightBundleResource;
use App\Filament\Resources\FlightBundles\Schemas\FlightBundleForm;
use App\Filament\Resources\FlightBundles\Tables\FlightBundlesTable;
use App\Filament\Resources\Tours\Pages\CreateTour;
use App\Filament\Resources\Tours\Pages\EditTour;
use App\Filament\Resources\Tours\Pages\ListTours;
use BackedEnum;
use Filafly\Icons\Phosphor\Enums\Phosphor;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Override;

/**
 * The tours half of `flight_bundles`, on its own nav item.
 *
 * Same model, same schemas and same relation managers as FlightBundleResource,
 * narrowed to `type = tour`: the type stops being a field the admin picks and
 * becomes the scope of the page. The bundles resource is untouched — it still
 * lists tours alongside everything else and still has its type filter.
 */
class TourResource extends FlightBundleResource
{
    protected static ?int $navigationSort = 2;

    protected static string|BackedEnum|null $navigationIcon = Phosphor::PathLight;

    protected static ?string $slug = 'tours';

    /** The bundles resource already answers a name search; two hits per row is noise. */
    protected static bool $isGloballySearchable = false;

    #[Override]
    public static function form(Schema $schema): Schema
    {
        return FlightBundleForm::configure($schema, forTours: true);
    }

    #[Override]
    public static function table(Table $table): Table
    {
        return FlightBundlesTable::configure($table, forTours: true);
    }

    #[Override]
    public static function getPages(): array
    {
        return [
            'index'  => ListTours::route('/'),
            'create' => CreateTour::route('/create'),
            'edit'   => EditTour::route('/{record}/edit'),
        ];
    }

    #[Override]
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('type', BundleType::Tour);
    }

    #[Override]
    public static function getModelLabel(): string
    {
        return __('filament.tours.label');
    }

    #[Override]
    public static function getPluralModelLabel(): string
    {
        return __('filament.tours.plural_label');
    }

    #[Override]
    public static function getNavigationLabel(): string
    {
        return __('filament.tours.plural_label');
    }
}

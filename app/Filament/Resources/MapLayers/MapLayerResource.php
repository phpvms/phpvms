<?php

declare(strict_types=1);

namespace App\Filament\Resources\MapLayers;

use App\Enums\NavigationGroup;
use App\Filament\Resources\MapLayers\Pages\CreateMapLayer;
use App\Filament\Resources\MapLayers\Pages\EditMapLayer;
use App\Filament\Resources\MapLayers\Pages\ListMapLayers;
use App\Filament\Resources\MapLayers\Schemas\MapLayerForm;
use App\Filament\Resources\MapLayers\Tables\MapLayersTable;
use App\Models\MapLayer;
use BackedEnum;
use Filafly\Icons\Phosphor\Enums\Phosphor;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Override;
use UnitEnum;

/**
 * Operator CRUD over overlay layers applied on top of the basemap (design.md
 * D9) — e.g. the seeded OpenAIP and METAR overlays. `type` is `raster` or
 * `vector` only; see {@see MapLayer}.
 */
class MapLayerResource extends Resource
{
    protected static ?string $model = MapLayer::class;

    protected static string|BackedEnum|null $navigationIcon = Phosphor::MapTrifoldLight;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Config;

    protected static ?int $navigationSort = 4;

    #[Override]
    public static function form(Schema $schema): Schema
    {
        return MapLayerForm::configure($schema);
    }

    #[Override]
    public static function table(Table $table): Table
    {
        return MapLayersTable::configure($table);
    }

    #[Override]
    public static function getPages(): array
    {
        return [
            'index'  => ListMapLayers::route('/'),
            'create' => CreateMapLayer::route('/create'),
            'edit'   => EditMapLayer::route('/{record}/edit'),
        ];
    }

    #[Override]
    public static function getModelLabel(): string
    {
        return __('map.layer');
    }

    #[Override]
    public static function getPluralModelLabel(): string
    {
        return __('map.layers');
    }
}

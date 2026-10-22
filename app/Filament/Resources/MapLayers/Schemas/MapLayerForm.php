<?php

declare(strict_types=1);

namespace App\Filament\Resources\MapLayers\Schemas;

use App\Models\MapLayer;
use Filafly\Icons\Phosphor\Enums\Phosphor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class MapLayerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('map.layer_name'))
                    ->required()
                    ->maxLength(255),

                Select::make('type')
                    ->label(__('map.layer_type'))
                    ->helperText(__('map.layer_type_hint'))
                    ->options([
                        MapLayer::TYPE_RASTER  => __('map.layer_type_raster'),
                        MapLayer::TYPE_VECTOR  => __('map.layer_type_vector'),
                        MapLayer::TYPE_BASEMAP => __('map.layer_type_basemap'),
                    ])
                    ->default(MapLayer::TYPE_RASTER)
                    ->required()
                    ->live()
                    ->native(false),

                // Not ->url(): Laravel's url rule rejects the {z}/{x}/{y} and
                // {bbox-epsg-3857} placeholders these templates carry — a WMS
                // endpoint seeds as a raster layer whose template holds the
                // query string (design.md D9), so the field stays free text.
                TextInput::make('url_template')
                    ->label(fn (Get $get): string => $get('type') === MapLayer::TYPE_BASEMAP
                        ? __('map.basemap_light')
                        : __('map.layer_url_template'))
                    ->helperText(fn (Get $get): string => $get('type') === MapLayer::TYPE_BASEMAP
                        ? __('map.basemap_light_hint')
                        : __('map.layer_url_template_hint'))
                    ->required()
                    ->columnSpanFull(),

                // Basemap only: a basemap is the one type with a theme variant,
                // because the surface swaps style with the panel theme. An
                // overlay has a single tile template and leaves this null.
                TextInput::make('url_template_dark')
                    ->label(__('map.basemap_dark'))
                    ->helperText(__('map.basemap_dark_hint'))
                    ->visible(fn (Get $get): bool => $get('type') === MapLayer::TYPE_BASEMAP)
                    ->required(fn (Get $get): bool => $get('type') === MapLayer::TYPE_BASEMAP)
                    ->columnSpanFull(),

                // A legal obligation, not decoration — every layer needs one.
                TextInput::make('attribution')
                    ->label(__('map.layer_attribution'))
                    ->helperText(__('map.layer_attribution_hint'))
                    ->required()
                    ->columnSpanFull(),

                TextInput::make('min_zoom')
                    ->label(__('map.layer_min_zoom'))
                    ->integer()
                    ->minValue(0)
                    ->maxValue(24)
                    ->default(0)
                    ->required()
                    ->hidden(fn (Get $get): bool => $get('type') === MapLayer::TYPE_BASEMAP),

                TextInput::make('max_zoom')
                    ->label(__('map.layer_max_zoom'))
                    ->integer()
                    ->minValue(0)
                    ->maxValue(24)
                    ->default(22)
                    ->required()
                    ->hidden(fn (Get $get): bool => $get('type') === MapLayer::TYPE_BASEMAP),

                TextInput::make('opacity')
                    ->label(__('map.layer_opacity'))
                    ->numeric()
                    ->step(0.01)
                    ->minValue(0)
                    ->maxValue(1)
                    ->default(1)
                    ->required()
                    ->hidden(fn (Get $get): bool => $get('type') === MapLayer::TYPE_BASEMAP),

                // Not a secret — any tile key is referrer-restricted and public
                // by construction, since the browser fetches the tiles
                // directly. See MapLayer's docblock (design.md D9).
                TextInput::make('api_key')
                    ->label(__('map.layer_api_key'))
                    ->helperText(__('map.layer_api_key_hint'))
                    ->hidden(fn (Get $get): bool => $get('type') === MapLayer::TYPE_BASEMAP),

                Toggle::make('enabled')
                    ->label(__('map.layer_enabled'))
                    ->default(true)
                    ->onIcon(Phosphor::CheckLight)
                    ->offIcon(Phosphor::XLight)
                    ->onColor('success')
                    ->offColor('danger'),
            ])
            ->columns(2);
    }
}

<?php

namespace App\Filament\Resources\Subfleets\Schemas;

use App\Enums\FlightType;
use App\Enums\FuelType;
use App\Models\Airport;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SubfleetForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('filament.subfleet_information'))
                    ->description(__('filament.subfleet_description'))
                    ->schema([
                        Select::make('airline_id')
                            ->label(__('common.airline'))
                            ->relationship('airline', 'name')
                            ->preload()
                            ->searchable()
                            ->required()
                            ->native(false),

                        Select::make('hub_id')
                            ->label(__('airports.home'))
                            ->relationship('home', 'icao')
                            ->preload()
                            ->getOptionLabelFromRecordUsing(fn (Airport $record): string => $record->icao.' - '.$record->name)
                            ->searchable()
                            ->native(false),

                        TextInput::make('type')
                            ->label(__('common.type'))
                            ->required()
                            ->string(),

                        TextInput::make('simbrief_type')
                            ->label(__('common.simbrief_airframe_id'))
                            ->string(),

                        TextInput::make('name')
                            ->label(__('common.name'))
                            ->required()
                            ->string(),

                        Select::make('fuel_type')
                            ->label(__('common.fuel_type'))
                            ->options(FuelType::class)
                            ->searchable()
                            ->native(false),

                        TextInput::make('cost_block_hour')
                            ->label('common.cost_per_hour')
                            ->minValue(0)
                            ->numeric()
                            ->step(0.01),

                        TextInput::make('cost_delay_minute')
                            ->label('common.cost_delay_per_minute')
                            ->minValue(0)
                            ->numeric()
                            ->step(0.01),

                        TextInput::make('ground_handling_multiplier')
                            ->label(__('common.expense_multiplier'))
                            ->helperText(__('filament.subfleet_expense_multiplier_hint'))
                            ->minValue(0)
                            ->integer(),
                    ])
                    ->columnSpanFull()
                    ->columns(3),

                Section::make(__('filament.subfleets.sections.operational_capability'))
                    ->schema([
                        TextInput::make('cruise_speed')
                            ->label(__('filament.subfleets.fields.cruise_speed'))
                            ->helperText(__('filament.subfleets.fields.cruise_speed_helper'))
                            ->suffix('kt')
                            ->integer()
                            ->minValue(0),

                        TextInput::make('max_range_nm')
                            ->label(__('filament.subfleets.fields.max_range_nm'))
                            ->helperText(__('filament.subfleets.fields.max_range_nm_helper'))
                            ->suffix('nm')
                            ->integer()
                            ->minValue(0),

                        Select::make('route_types')
                            ->label(__('filament.subfleets.fields.route_types'))
                            ->helperText(__('filament.subfleets.fields.route_types_helper'))
                            ->multiple()
                            ->options(FlightType::class)
                            ->native(false),
                    ])
                    ->columnSpanFull()
                    ->columns(3),
            ]);
    }
}

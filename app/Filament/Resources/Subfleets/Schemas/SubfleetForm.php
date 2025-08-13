<?php

namespace App\Filament\Resources\Subfleets\Schemas;

use App\Models\Airport;
use App\Models\Enums\FuelType;
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
                Section::make(__('filament.subfleet_informations'))
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
                            ->options(FuelType::labels())
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
            ]);
    }
}

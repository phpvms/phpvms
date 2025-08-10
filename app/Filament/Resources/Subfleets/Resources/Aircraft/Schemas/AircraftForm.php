<?php

namespace App\Filament\Resources\Subfleets\Resources\Aircraft\Schemas;

use App\Models\Airport;
use App\Models\Enums\AircraftStatus;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AircraftForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('filament.aircraft_informations'))
                    ->schema([
                        TextInput::make('name')
                            ->label(__('common.name'))
                            ->required()
                            ->string(),

                        TextInput::make('registration')
                            ->label(__('aircraft.registration'))
                            ->required()
                            ->string(),

                        TextInput::make('icao')
                            ->label('ICAO')
                            ->string(),

                        Select::make('status')
                            ->label(__('common.status'))
                            ->options(AircraftStatus::labels())
                            ->required()
                            ->native(false),

                        Select::make('hub_id')
                            ->label(__('airports.home'))
                            ->relationship('home', 'icao')
                            ->getOptionLabelFromRecordUsing(fn (Airport $record): string => $record->icao.' - '.$record->name)
                            ->searchable()
                            ->native(false),

                        Select::make('airport_id')
                            ->label(__('airports.current'))
                            ->relationship('airport', 'icao')
                            ->getOptionLabelFromRecordUsing(fn (Airport $record): string => $record->icao.' - '.$record->name)
                            ->searchable()
                            ->native(false),

                        TextInput::make('selcal')
                            ->label('SELCAL')
                            ->string(),

                        TextInput::make('iata')
                            ->label('IATA')
                            ->string(),

                        TextInput::make('fin')
                            ->label('FIN')
                            ->string(),

                        TextInput::make('simbrief_type')
                            ->label(__('common.simbrief_airframe_id'))
                            ->string(),

                        TextInput::make('hex_code')
                            ->label(__('aircraft.hex_code'))
                            ->string(),
                    ])
                    ->columnSpanFull()
                    ->columns(4),

                Section::make(__('filament.certified_weights'))
                    ->schema([
                        TextInput::make('dow')
                            ->label(__('aircraft.weights.dow'))
                            ->numeric(),

                        TextInput::make('zfw')
                            ->label(__('aircraft.weights.mzfw'))
                            ->numeric(),

                        TextInput::make('mtow')
                            ->label(__('aircraft.weights.mtow'))
                            ->numeric(),

                        TextInput::make('mlw')
                            ->label(__('aircraft.weights.mlw'))
                            ->numeric(),
                    ])
                    ->columnSpanFull()
                    ->columns(4),
            ]);
    }
}

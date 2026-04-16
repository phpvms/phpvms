<?php

namespace App\Filament\Resources\Pireps\Schemas;

use App\Models\Airport;
use App\Models\Enums\FlightType;
use App\Models\Enums\PirepSource;
use App\Models\Pirep;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PirepForm
{
    public static function configure(Schema $schema): Schema
    {

        return $schema
            ->components([
                Section::make(__('filament.basic_informations'))->schema([

                    TextInput::make('flight_number')
                        ->integer()
                        ->maxLength(4)
                        ->label(__('flights.flightnumber')),

                    TextInput::make('route_code')
                        ->label(__('flights.routecode')),

                    TextInput::make('route_leg')
                        ->label(__('flights.routeleg')),

                    Select::make('flight_type')
                        ->label(__('flights.flighttype'))
                        ->disabled(false)
                        ->options(FlightType::select())
                        ->native(false),

                    TextEntry::make('source')
                        ->label(__('pireps.source'))
                        ->state(fn (Pirep $record): string => PirepSource::label($record->source).(filled($record->source_name) ? '('.$record->source_name.')' : '')),
                ])
                    ->columns(5)
                    ->columnSpanFull()
                    ->disabled(fn (Pirep $record): bool => $record->read_only),

                Grid::make()->schema([
                    Section::make(__('filament.pirep_details'))->schema([
                        Grid::make()->schema([
                            Select::make('airline_id')
                                ->label(__('common.airline'))
                                ->relationship('airline', 'name')
                                ->native(false)
                                ->disabled(fn (Pirep $record): bool => $record->read_only),

                            Select::make('aircraft_id')
                                ->label(__('common.aircraft'))
                                ->relationship('aircraft', 'name')
                                ->native(false)
                                ->disabled(fn (Pirep $record): bool => $record->read_only),

                            TimePicker::make('flight_time')
                                ->label(__('pireps.flighttime'))
                                ->seconds(false)
                                ->native(false),

                            Grid::make()->schema([
                                Select::make('dpt_airport_id')
                                    ->label(__('airports.departure'))
                                    ->relationship('dpt_airport', 'icao')
                                    ->getOptionLabelFromRecordUsing(fn (Airport $record): string => $record->icao.' - '.$record->name)
                                    ->searchable()
                                    ->native(false)
                                    ->columnSpan(1)
                                    ->disabled(fn (Pirep $record): bool => $record->read_only),

                                Select::make('arr_airport_id')
                                    ->label(__('airports.arrival'))
                                    ->relationship('arr_airport', 'icao')
                                    ->getOptionLabelFromRecordUsing(fn (Airport $record): string => $record->icao.' - '.$record->name)
                                    ->searchable()
                                    ->native(false)
                                    ->columnSpan(1)
                                    ->disabled(fn (Pirep $record): bool => $record->read_only),
                            ])
                                ->columns(2)
                                ->columnSpan(3),

                            TextInput::make('block_fuel')
                                ->numeric()
                                ->label(__('pireps.block_fuel'))
                                ->hint(__('common.in_lbs')),

                            TextInput::make('fuel_used')
                                ->numeric()
                                ->label(__('pireps.fuel_used'))
                                ->hint(__('common.in_lbs')),

                            TextInput::make('level')
                                ->hint('In ft')
                                ->label('Flight Level'),

                            TextInput::make('distance')
                                ->numeric()
                                ->label(__('common.distance'))
                                ->hint(__('common.in_nautical_miles')),

                            TextInput::make('score')
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(100)
                                ->label(__('pireps.score')),
                        ])
                            ->columnSpanFull()
                            ->columns(3),

                        Textarea::make('route')
                            ->autosize()
                            ->label(__('flights.route')),

                        RichEditor::make('notes')
                            ->label(__('common.notes')),
                    ])
                        ->columnSpanFull()
                        ->columnSpan(2),

                    Section::make(__('filament.planned_details'))->schema([
                        TimePicker::make('planned_flight_time')
                            ->label(__('pireps.planned_flight_time'))
                            ->seconds(false)
                            ->native(false),

                        TextInput::make('level')
                            ->hint(__('common.in_feet'))
                            ->label(__('pireps.planned_level')),

                        TextInput::make('planned_distance')
                            ->label(__('pireps.planned_distance'))
                            ->hint(__('common.in_nautical_miles')),

                        TextInput::make('landing_rate')
                            ->label(__('pireps.landing_rate'))
                            ->hint(__('common.in_feet_per_minute')),
                    ])
                        ->disabled()
                        ->columnSpan(1),
                ])
                    ->columnSpanFull()
                    ->columns(3),
            ]);
    }
}

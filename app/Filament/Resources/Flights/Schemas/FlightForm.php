<?php

namespace App\Filament\Resources\Flights\Schemas;

use App\Models\Airport;
use App\Models\Enums\Days;
use App\Models\Enums\FlightType;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class FlightForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make()->schema([
                    Section::make(__('filament.flight_informations'))
                        ->schema([
                            Select::make('airline_id')
                                ->label(__('common.airline'))
                                ->relationship('airline', 'name')
                                ->searchable()
                                ->required()
                                ->preload()
                                ->native(false),

                            Select::make('flight_type')
                                ->label(__('flights.flighttype'))
                                ->searchable()
                                ->native(false)
                                ->required()
                                ->options(FlightType::select()),

                            TextInput::make('callsign')
                                ->label(__('flights.callsign'))
                                ->string()
                                ->maxLength(4),

                            TextInput::make('flight_number')
                                ->label(__('flights.flightnumber'))
                                ->integer()
                                ->maxLength(4)
                                ->required(),

                            TextInput::make('route_code')
                                ->label(__('flights.routecode'))
                                ->string()
                                ->maxLength(5),

                            TextInput::make('route_leg')
                                ->label(__('flights.routeleg'))
                                ->integer(),

                            TimePicker::make('flight_time')
                                ->seconds(false)
                                ->label(__('flights.flighttime'))
                                ->native(false)
                                ->required(),

                            TextInput::make('pilot_pay')
                                ->label(__('flights.pilotpay'))
                                ->numeric()
                                ->helperText(__('filament.flight_pilot_pay_hint')),

                            Grid::make()->schema([
                                TextInput::make('load_factor')
                                    ->numeric()
                                    ->helperText(__('filament.flight_load_factor_hint')),

                                TextInput::make('load_factor_variance')
                                    ->numeric()
                                    ->helperText(__('filament.flight_load_factor_variance_hint')),

                            ])
                                ->columnSpanFull()
                                ->columnSpan(3),
                        ])
                        ->columns(3)
                        ->columnSpan(['lg' => 2, 'default' => 'full']),

                    Section::make(__('filament.scheduling'))
                        ->schema([
                            DatePicker::make('start_date')
                                ->label(__('common.start_date'))
                                ->live()
                                ->native(false)
                                ->minDate(now()),

                            DatePicker::make('end_date')
                                ->label(__('common.end_date'))
                                ->native(false)
                                ->minDate(fn (Get $get): Carbon|string => $get('start_date') ?? now()),

                            Select::make('days')
                                ->label(__('common.days_text'))
                                ->options(Days::labels())
                                ->multiple()
                                ->native(false),

                            TimePicker::make('dpt_time')
                                ->seconds(false)
                                ->label(__('flights.departuretime')),

                            TimePicker::make('arr_time')
                                ->seconds(false)
                                ->label(__('flights.arrivaltime')),
                        ])
                        ->columnSpan(1),
                ])
                    ->columnSpanFull()
                    ->columns(3),

                Section::make(__('flights.route'))
                    ->schema([
                        Grid::make()->schema([
                            Select::make('dpt_airport_id')
                                ->label(__('airports.departure'))
                                ->relationship('dpt_airport', titleAttribute: 'icao')
                                ->getOptionLabelFromRecordUsing(fn (Airport $record): string => $record->icao.' - '.$record->name)
                                ->searchable()
                                ->required()
                                ->preload()
                                ->native(false),

                            Select::make('arr_airport_id')
                                ->label(__('airports.arrival'))
                                ->relationship('arr_airport', titleAttribute: 'icao')
                                ->getOptionLabelFromRecordUsing(fn (Airport $record): string => $record->icao.' - '.$record->name)
                                ->searchable()
                                ->required()
                                ->preload()
                                ->native(false),
                        ])
                            ->columnSpanFull()
                            ->columns(2),

                        Textarea::make('route')
                            ->label(__('flights.route')),

                        Grid::make()->schema([
                            Select::make('alt_airport_id')
                                ->label(__('flights.alternateairport'))
                                ->relationship('alt_airport', titleAttribute: 'icao')
                                ->getOptionLabelFromRecordUsing(fn (Airport $record): string => $record->icao.' - '.$record->name)
                                ->searchable()
                                ->preload()
                                ->native(false),

                            TextInput::make('level')
                                ->label(__('flights.level'))
                                ->integer()
                                ->hint(__('common.in_feet')),

                            TextInput::make('distance')
                                ->integer()
                                ->hint(__('common.in_nautical_miles')),
                        ])
                            ->columnSpanFull()
                            ->columns(3),
                    ])
                    ->columnSpanFull(),

                Section::make(trans_choice('common.remark', 2))
                    ->schema([
                        RichEditor::make('notes')
                            ->label(__('common.notes'))
                            ->columnSpanFull(),

                        Toggle::make('active')
                            ->label(__('common.active'))
                            ->offIcon(Heroicon::XCircle)
                            ->offColor('danger')
                            ->onIcon(Heroicon::CheckCircle)
                            ->onColor('success'),

                        Toggle::make('visible')
                            ->label(__('common.visible'))
                            ->offIcon(Heroicon::XCircle)
                            ->offColor('danger')
                            ->onIcon(Heroicon::CheckCircle)
                            ->onColor('success'),
                    ])
                    ->columnSpanFull()
                    ->columns(2),
            ]);
    }
}

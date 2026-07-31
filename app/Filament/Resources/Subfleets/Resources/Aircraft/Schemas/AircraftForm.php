<?php

namespace App\Filament\Resources\Subfleets\Resources\Aircraft\Schemas;

use App\Enums\AircraftStatus;
use App\Models\Airport;
use App\Models\SimBriefAirframe;
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
                Section::make(__('filament.aircraft_information'))
                    ->schema([
                        TextInput::make('name')
                            ->label(__('common.name'))
                            ->required()
                            ->string(),

                        TextInput::make('registration')
                            ->label(__('aircraft.registration'))
                            ->required()
                            ->string(),

                        Select::make('icao')
                            ->label('ICAO')
                            ->searchable()
                            ->native(false)
                            ->getSearchResultsUsing(fn (string $search): array => SimBriefAirframe::query()
                                ->where(function ($query) use ($search): void {
                                    $query->where('icao', 'like', sprintf('%%%s%%', $search))
                                        ->orWhere('name', 'like', sprintf('%%%s%%', $search));
                                })
                                ->orderBy('icao')
                                ->limit(50)
                                ->get()
                                ->mapWithKeys(fn (SimBriefAirframe $airframe): array => [$airframe->icao => $airframe->icao.' - '.$airframe->name])
                                ->all())
                            ->getOptionLabelUsing(function (?string $value): ?string {
                                if (blank($value)) {
                                    return null;
                                }

                                $name = SimBriefAirframe::where('icao', $value)->value('name');

                                return filled($name) ? $value.' - '.$name : $value;
                            }),

                        Select::make('status')
                            ->label(__('common.status'))
                            ->options(AircraftStatus::class)
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

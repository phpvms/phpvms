<?php

namespace App\Filament\Resources\Airlines\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use League\ISO3166\ISO3166;

class AirlineForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('filament.airline_informations'))
                    ->schema([
                        TextInput::make('icao')
                            ->label('ICAO (3LD)')
                            ->required()
                            ->string()
                            ->length(3),

                        TextInput::make('iata')
                            ->label('IATA (2LD)')
                            ->string()
                            ->length(2),

                        TextInput::make('callsign')
                            ->label(__('flights.callsign'))
                            ->string(),

                        TextInput::make('name')
                            ->label(__('common.name'))
                            ->required()
                            ->string(),

                        TextInput::make('logo')
                            ->label(__('common.image_url'))
                            ->string(),

                        Select::make('country')
                            ->label(label: __('common.country'))
                            ->options(collect((new ISO3166())->all())->mapWithKeys(fn (array $item, string $key) => [strtolower($item['alpha2']) => str_replace('&bnsp;', ' ', $item['name'])]))
                            ->searchable()
                            ->native(false),

                        Toggle::make('active')
                            ->label(label: __('common.active'))
                            ->inline()
                            ->onColor('success')
                            ->onIcon(Heroicon::CheckCircle)
                            ->offColor('danger')
                            ->offIcon(Heroicon::XCircle),
                    ])
                    ->columnSpanFull()
                    ->columns(3),
            ]);
    }
}

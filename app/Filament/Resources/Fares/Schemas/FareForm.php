<?php

namespace App\Filament\Resources\Fares\Schemas;

use App\Enums\FareType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class FareForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('filament.fare_information'))
                    ->description(__('filament.fare_description'))
                    ->schema([
                        TextInput::make('code')
                            ->label(__('flights.code'))
                            ->required()
                            ->string(),

                        TextInput::make('name')
                            ->label(__('common.name'))
                            ->required()
                            ->string(),
                        Select::make('type')
                            ->label(__('common.type'))
                            ->options(FareType::class)
                            ->required()
                            ->native(false),

                        TextInput::make('price')
                            ->label(__('common.price'))
                            ->helperText(__('filament.fare_price_hint'))
                            ->numeric(),

                        TextInput::make('base_price')
                            ->label(__('filament.fare_base_price'))
                            ->helperText(__('filament.fare_base_price_hint'))
                            ->numeric()
                            ->minValue(0)
                            ->default(0),

                        TextInput::make('per_nm')
                            ->label(__('filament.fare_per_nm'))
                            ->helperText(__('filament.fare_per_nm_hint'))
                            ->numeric()
                            ->minValue(0)
                            ->step(0.0001)
                            ->default(0),

                        TextInput::make('multiplier')
                            ->label(__('filament.fare_multiplier'))
                            ->helperText(__('filament.fare_multiplier_hint'))
                            ->numeric()
                            ->minValue(0)
                            ->step(0.0001)
                            ->default(1),

                        TextInput::make('cost')
                            ->label(__('common.cost'))
                            ->helperText(__('filament.fare_cost_hint'))
                            ->numeric(),

                        TextInput::make('capacity')
                            ->label(__('common.capacity'))
                            ->helperText(__('filament.fare_capacity_hint'))
                            ->numeric(),

                        Textarea::make('notes')
                            ->label(__('common.notes'))
                            ->columnSpanFull(),

                        Toggle::make('active')
                            ->label(__('common.active'))
                            ->offIcon(Heroicon::XCircle)
                            ->offColor('danger')
                            ->onIcon(Heroicon::CheckCircle)
                            ->onColor('success'),
                    ])
                    ->columnSpanFull()
                    ->columns(columns: 3),
            ]);
    }
}

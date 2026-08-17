<?php

namespace App\Filament\Resources\Expenses\Schemas;

use App\Enums\ExpenseType;
use App\Enums\FlightType;
use Filafly\Icons\Phosphor\Enums\Phosphor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class ExpenseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make()->schema([
                    Select::make('airline_id')
                        ->relationship('airline', 'name')
                        ->searchable()
                        ->preload()
                        ->native(false)
                        ->label(__('common.airline')),

                    Select::make('type')
                        ->options(ExpenseType::class)
                        ->searchable()
                        ->native(false)
                        ->required()
                        ->label(__('common.type')),

                    Select::make('flight_type')
                        ->options(FlightType::class)
                        ->searchable()
                        ->native(false)
                        ->multiple()
                        ->label(__('flights.flighttype')),
                ])
                    ->columnSpanFull()
                    ->columns(3),

                Grid::make()->schema([
                    TextInput::make('name')
                        ->required()
                        ->string()
                        ->label(__('common.name')),

                    TextInput::make('amount')
                        ->required()
                        ->numeric()
                        ->label(__('common.amount')),

                    Toggle::make('multiplier')
                        ->label(__('common.multiplier'))
                        ->inline()
                        ->onColor('success')
                        ->onIcon(Phosphor::CheckLight)
                        ->offColor('danger')
                        ->offIcon(Phosphor::XLight),

                    Toggle::make('active')
                        ->inline()
                        ->onColor('success')
                        ->onIcon(Phosphor::CheckLight)
                        ->offColor('danger')
                        ->offIcon(Phosphor::XLight),
                ])
                    ->columnSpanFull()
                    ->columns(2),
            ]);
    }
}

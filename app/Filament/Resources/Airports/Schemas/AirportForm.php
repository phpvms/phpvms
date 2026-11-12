<?php

namespace App\Filament\Resources\Airports\Schemas;

use App\Filament\Resources\Airports\Actions\LookupAction;
use App\Support\Timezonelist;
use Filafly\Icons\Phosphor\Enums\Phosphor;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use League\ISO3166\ISO3166;

class AirportForm
{
    /**
     * The page and create form: one aside section per topic. The list
     * modals use the bare components() instead.
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('filament.airport_information'))
                    ->schema([
                        self::icao(),
                        self::iata(),
                        self::name(),
                        self::locationName(),
                        self::region(),
                        self::hub(),
                        self::notes(),
                    ])
                    ->aside()
                    ->description(__('filament.airport_information_description'))
                    ->columnSpanFull()
                    ->columns(2),

                Section::make(__('filament.airport_position'))
                    ->schema([
                        self::lat(),
                        self::lon(),
                        self::elevation(),
                        self::country(),
                        self::timezone(),
                    ])
                    ->aside()
                    ->description(__('filament.airport_position_description'))
                    ->columnSpanFull()
                    ->columns(3),

                Section::make(__('filament.airport_costs'))
                    ->schema([
                        self::groundHandlingCost(),
                        self::fuelJetaCost(),
                        self::fuel100llCost(),
                        self::fuelMogasCost(),
                    ])
                    ->aside()
                    ->description(__('filament.airport_costs_description'))
                    ->columnSpanFull()
                    ->columns(2),
            ]);
    }

    /**
     * @return array<int, Component>
     */
    public static function components(): array
    {
        return [
            Grid::make([
                'default' => 4,
            ])
                ->schema([
                    self::icao()->columnSpan(2),
                    self::iata()->columnSpan(2),
                    self::name(),
                    self::lat(),
                    self::lon(),
                    self::elevation(),
                    self::country(),
                    self::locationName(),
                    self::region(),
                    self::timezone(),
                    self::groundHandlingCost(),
                    self::fuelJetaCost(),
                    self::fuel100llCost(),
                    self::fuelMogasCost(),
                    self::notes()->columnSpan(4),
                    self::hub(),
                ])
                ->columnSpanFull()
                ->columns(4),
        ];
    }

    private static function icao(): TextInput
    {
        return TextInput::make('icao')
            ->label('ICAO')
            ->required()
            ->string()
            ->maxLength(8)
            ->extraInputAttributes([
                'x-on:keydown.enter.prevent' => "document.getElementById('airport-icao-lookup')?.click()",
            ])
            ->hintAction(LookupAction::make());
    }

    private static function iata(): TextInput
    {
        return TextInput::make('iata')
            ->label('IATA')
            ->string()
            ->maxLength(8);
    }

    private static function name(): TextInput
    {
        return TextInput::make('name')
            ->label(__('common.name'))
            ->required()
            ->string();
    }

    private static function lat(): TextInput
    {
        return TextInput::make('lat')
            ->label(__('airports.latitude'))
            ->required()
            ->minValue(-90)
            ->maxValue(90)
            ->numeric();
    }

    private static function lon(): TextInput
    {
        return TextInput::make('lon')
            ->label(__('airports.longitude'))
            ->required()
            ->minValue(-180)
            ->maxValue(180)
            ->numeric();
    }

    private static function elevation(): TextInput
    {
        return TextInput::make('elevation')
            ->hint(__('common.in_feet'))
            ->label(__('airports.elevation'))
            ->numeric();
    }

    private static function country(): Select
    {
        return Select::make('country')
            ->label(label: __('common.country'))
            ->options(collect(new ISO3166()->all())->mapWithKeys(fn (array $item, string $key): array => [strtolower($item['alpha2']) => str_replace('&bnsp;', ' ', $item['name'])]))
            ->searchable()
            ->optionsLimit(300)
            ->native(false);
    }

    private static function locationName(): TextInput
    {
        return TextInput::make('location')
            ->label(__('user.location'))
            ->string();
    }

    private static function region(): TextInput
    {
        return TextInput::make('region')
            ->label(__('airports.region'))
            ->string();
    }

    private static function timezone(): Select
    {
        return Select::make('timezone')
            ->label(__(key: 'common.timezone'))
            ->options(Timezonelist::toArray())
            ->searchable()
            ->allowHtml()
            ->native(false);
    }

    private static function groundHandlingCost(): TextInput
    {
        return TextInput::make('ground_handling_cost')
            ->label(__('airports.ground_handling_cost'))
            ->helperText(__('airports.ground_handling_cost_helper'))
            ->numeric();
    }

    private static function fuelJetaCost(): TextInput
    {
        return TextInput::make('fuel_jeta_cost')
            ->label(__('airports.fuel_jeta_cost'))
            ->helperText(__('airports.cost_per_lbs'))
            ->numeric();
    }

    private static function fuel100llCost(): TextInput
    {
        return TextInput::make('fuel_100ll_cost')
            ->label(__('airports.fuel_100ll_cost'))
            ->helperText(__('airports.cost_per_lbs'))
            ->numeric();
    }

    private static function fuelMogasCost(): TextInput
    {
        return TextInput::make('fuel_mogas_cost')
            ->label(__('airports.fuel_mogas_cost'))
            ->helperText(__('airports.cost_per_lbs'))
            ->numeric();
    }

    private static function notes(): RichEditor
    {
        return RichEditor::make('notes')
            ->label(trans_choice('common.notes', 2))
            ->columnSpanFull();
    }

    private static function hub(): Toggle
    {
        return Toggle::make('hub')
            ->label(__('airports.hub'))
            ->columnSpanFull()
            ->inlineLabel()
            ->offIcon(Phosphor::XLight)
            ->offColor('danger')
            ->onIcon(Phosphor::CheckLight)
            ->onColor('success');
    }
}

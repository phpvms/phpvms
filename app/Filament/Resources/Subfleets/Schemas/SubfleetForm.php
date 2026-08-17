<?php

namespace App\Filament\Resources\Subfleets\Schemas;

use App\Enums\FlightType;
use App\Enums\FuelType;
use App\Filament\Forms\Components\AirportSelect;
use App\Models\SimBriefAirframe;
use App\Models\Subfleet;
use Filafly\Icons\Phosphor\Enums\Phosphor;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class SubfleetForm
{
    public static function configure(Schema $schema, bool $withIdentity = true, bool $withHomeAirport = true): Schema
    {
        return $schema
            ->components([
                Section::make(__('filament.subfleet_information'))
                    ->id('subfleet-information')
                    ->icon(Phosphor::StackSimpleLight)
                    ->collapsible()
                    ->persistCollapsed()
                    ->schema([
                        // On the edit page these live in the overview's drawer
                        // instead; the create page still needs them inline,
                        // because there is no overview to edit yet.
                        ...($withIdentity ? self::identityFields() : []),

                        ...($withHomeAirport ? [self::homeAirportField()] : []),

                        Select::make('simbrief_type')
                            ->label(__('common.simbrief_airframe_id'))
                            ->searchable()
                            ->native(false)
                            // The opening list, before anything is typed:
                            // airframes for this subfleet's type. Filament
                            // hands `options` to the JS regardless of the
                            // search callback, so the two coexist.
                            ->options(fn (Get $get, ?Subfleet $record): array => self::airframeOptions($get('type') ?? $record?->type))
                            ->getSearchResultsUsing(fn (string $search): array => SimBriefAirframe::query()
                                ->whereNotNull('airframe_id')
                                ->where('airframe_id', '!=', '')
                                ->where(function ($query) use ($search): void {
                                    $query->where('name', 'like', sprintf('%%%s%%', $search))
                                        ->orWhere('icao', 'like', sprintf('%%%s%%', $search))
                                        ->orWhere('airframe_id', 'like', sprintf('%%%s%%', $search));
                                })
                                ->orderBy('name')
                                ->limit(50)
                                ->get()
                                ->mapWithKeys(fn (SimBriefAirframe $airframe): array => [$airframe->airframe_id => $airframe->name.' ('.$airframe->icao.')'])
                                ->all())
                            ->getOptionLabelUsing(function (?string $value): ?string {
                                if (blank($value)) {
                                    return null;
                                }

                                $airframe = SimBriefAirframe::where('airframe_id', $value)->first(['name', 'icao']);

                                return $airframe ? $airframe->name.' ('.$airframe->icao.')' : $value;
                            }),

                        Select::make('fuel_type')
                            ->label(__('common.fuel_type'))
                            ->options(FuelType::class)
                            ->searchable()
                            ->native(false),

                        // Nested Sections render as sequent head bands inside
                        // the same card, not as second and third cards.
                        Section::make(__('filament.subfleets.sections.costs'))
                            ->id('costs')
                            ->icon(Phosphor::MoneyLight)
                            ->collapsible()
                            ->persistCollapsed()
                            ->columns(3)
                            ->schema([
                                TextInput::make('cost_block_hour')
                                    ->label(__('common.cost_per_hour'))
                                    ->minValue(0)
                                    ->numeric()
                                    ->step(0.01),

                                TextInput::make('cost_delay_minute')
                                    ->label(__('common.cost_delay_per_minute'))
                                    ->minValue(0)
                                    ->numeric()
                                    ->step(0.01),

                                TextInput::make('ground_handling_multiplier')
                                    ->label(__('common.expense_multiplier'))
                                    ->helperText(__('filament.subfleet_expense_multiplier_hint'))
                                    ->minValue(0)
                                    ->integer(),
                            ])
                            ->columnSpanFull(),

                        Section::make(__('filament.subfleets.sections.operational_capability'))
                            ->id('operational-capability')
                            ->icon(Phosphor::MapTrifoldLight)
                            ->collapsible()
                            ->persistCollapsed()
                            ->columns(3)
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

                                self::routeTypesField(),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),
            ]);
    }

    /**
     * @return array<int, Field>
     */
    public static function createFields(): array
    {
        return [
            ...self::identityFields(),
            self::homeAirportField(),
            self::routeTypesField(),
        ];
    }

    /**
     * @return array<int, Field>
     */
    public static function editDrawerFields(): array
    {
        return [
            ...self::identityFields(),
            self::homeAirportField(),
        ];
    }

    /**
     * The airframes offered before the admin searches. Scoped to the
     * subfleet's type, falling back to a general slice when the type matches
     * nothing — an empty dropdown is worse than an unfiltered one.
     *
     * @return array<string, string>
     */
    private static function airframeOptions(?string $type): array
    {
        $query = fn (?string $forType): Builder => SimBriefAirframe::query()
            ->forType($forType)
            ->whereNotNull('airframe_id')
            ->where('airframe_id', '!=', '')
            ->orderBy('name')
            ->limit(50);

        $airframes = $query($type)->get();

        if ($airframes->isEmpty()) {
            $airframes = $query(null)->get();
        }

        return $airframes
            ->mapWithKeys(fn (SimBriefAirframe $airframe): array => [$airframe->airframe_id => $airframe->name.' ('.$airframe->icao.')'])
            ->all();
    }

    /**
     * Airline, name and type: read in the edit page's overview, edited in the
     * drawer it opens.
     *
     * @return array<int, Field>
     */
    public static function identityFields(): array
    {
        return [
            Select::make('airline_id')
                ->label(__('common.airline'))
                ->relationship('airline', 'name')
                ->preload()
                ->searchable()
                ->required()
                ->native(false),

            TextInput::make('name')
                ->label(__('common.name'))
                ->required()
                ->string(),

            // Live so the create page's airframe list narrows to the type as
            // soon as it is entered.
            TextInput::make('type')
                ->label(__('common.type'))
                ->required()
                ->live(onBlur: true)
                ->string(),
        ];
    }

    private static function homeAirportField(): AirportSelect
    {
        return AirportSelect::make('hub_id')
            ->label(__('airports.home'))
            ->airportRelationship('home');
    }

    private static function routeTypesField(): Select
    {
        return Select::make('route_types')
            ->label(__('filament.subfleets.fields.route_types'))
            ->helperText(__('filament.subfleets.fields.route_types_helper'))
            ->multiple()
            ->options(FlightType::class)
            ->native(false);
    }
}

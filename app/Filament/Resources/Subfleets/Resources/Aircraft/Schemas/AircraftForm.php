<?php

namespace App\Filament\Resources\Subfleets\Resources\Aircraft\Schemas;

use App\Enums\AircraftStatus;
use App\Filament\Forms\Components\AirportSelect;
use App\Models\Aircraft;
use App\Models\SimBriefAirframe;
use Filafly\Icons\Phosphor\Enums\Phosphor;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class AircraftForm
{
    public static function configure(Schema $schema, bool $withIdentity = true): Schema
    {
        return $schema
            ->components([
                Section::make(__('filament.aircraft_information'))
                    ->id('aircraft-information')
                    ->icon(Phosphor::AirplaneLight)
                    ->collapsible()
                    ->persistCollapsed()
                    ->schema([
                        // On the edit page these live in the overview's drawer
                        // instead; the create page still needs them inline,
                        // because there is no overview to edit yet.
                        ...($withIdentity ? self::identityFields() : []),

                        AirportSelect::make('hub_id')
                            ->label(__('airports.home'))
                            ->airportRelationship('home'),

                        AirportSelect::make('airport_id')
                            ->label(__('airports.current'))
                            ->airportRelationship('airport'),

                        // Nested Sections render as sequent head bands inside
                        // the same card, not as second and third cards.
                        Section::make(__('filament.aircraft_identifiers'))
                            ->id('identifiers')
                            ->icon(Phosphor::HashLight)
                            ->collapsible()
                            ->persistCollapsed()
                            ->columns(4)
                            ->schema([
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
                            ->columnSpanFull(),

                        Section::make(__('filament.certified_weights'))
                            ->id('certified-weights')
                            ->icon(Phosphor::ScalesLight)
                            ->collapsible()
                            ->persistCollapsed()
                            ->columns(4)
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
                            ->columnSpanFull(),
                    ])
                    ->columns(4)
                    ->columnSpanFull(),
            ]);
    }

    /**
     * The ICAO codes offered before the admin searches. Scoped to the
     * subfleet's type, falling back to a general slice when the type matches
     * nothing — an empty dropdown is worse than an unfiltered one.
     *
     * @return array<string, string>
     */
    private static function icaoOptions(?string $type): array
    {
        $query = fn (?string $forType): Builder => SimBriefAirframe::query()
            ->forType($forType)
            ->orderBy('icao')
            ->limit(50);

        $airframes = $query($type)->get();

        if ($airframes->isEmpty()) {
            $airframes = $query(null)->get();
        }

        return $airframes
            ->mapWithKeys(fn (SimBriefAirframe $airframe): array => [$airframe->icao => $airframe->icao.' - '.$airframe->name])
            ->all();
    }

    /**
     * Name, registration, ICAO and status: read in the edit page's overview,
     * edited in the drawer it opens.
     *
     * @return array<int, Field>
     */
    public static function identityFields(): array
    {
        return [
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
                // The opening list, before anything is typed: the type codes
                // of the subfleet this aircraft sits in. Filament hands
                // `options` to the JS regardless of the search callback, so
                // the two coexist.
                ->options(fn (?Aircraft $record, Page $livewire): array => self::icaoOptions(
                    $record?->subfleet?->type ?: $livewire->getParentRecord()?->type,
                ))
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
        ];
    }
}

<?php

namespace App\Filament\Resources\FlightBundles\Resources\Flight\Schemas;

use App\Enums\FlightType;
use App\Filament\Forms\Components\AirportSelect;
use App\Filament\Forms\StateCasts\DaysMaskStateCast;
use App\Filament\Resources\FlightBundles\FlightBundleResource;
use App\Models\Airport;
use App\Models\Flight;
use App\Models\FlightBundle;
use App\Services\AirportService;
use App\Support\Days;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Daljo25\FilamentTablerIcons\Enums\TablerIcon;
use DateTimeInterface;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Throwable;

class FlightForm
{
    public static function configure(Schema $schema, bool $withIdentity = true): Schema
    {
        return $schema
            ->components([
                Section::make(__('filament.flight_information'))
                    ->id('flight-information')
                    ->icon(TablerIcon::FileText)
                    ->collapsible()
                    ->persistCollapsed()
                    ->footer([
                        Toggle::make('enabled')
                            ->inline()
                            ->label(__('common.enabled'))
                            ->helperText(__('filament.flight_enabled_hint'))
                            ->offIcon(TablerIcon::X)
                            ->offColor('danger')
                            ->onIcon(TablerIcon::Check)
                            ->onColor('success')
                            ->default(true),
                    ])
                    ->schema([
                        // On the edit page these live in the overview's
                        // drawer instead; the create page still needs them
                        // inline, because there is no overview to edit yet.
                        ...($withIdentity ? self::identityFields() : []),

                        TextInput::make('callsign')
                            ->label(__('flights.callsign'))
                            ->string()
                            ->maxLength(10),

                        TextInput::make('pilot_pay')
                            ->label(__('flights.pilotpay'))
                            ->numeric()
                            ->helperText(__('filament.flight_pilot_pay_hint')),

                        // Nested Sections render as sequent head bands inside
                        // the same card, like the PIREP view's stacked
                        // .panel__head sections -- not a second card.
                        Section::make(__('flights.route'))
                            ->id('route')
                            ->icon(TablerIcon::Map)
                            ->collapsible()
                            ->persistCollapsed()
                            ->schema([
                                View::make('filament.resources.flight.route-bar')
                                    ->viewData(fn (Get $get): array => self::routeBarData($get))
                                    ->visible(fn (?Flight $record): bool => $record instanceof Flight)
                                    ->columnSpanFull(),

                                Grid::make()->schema([
                                    AirportSelect::make('dpt_airport_id')
                                        ->label(__('airports.departure'))
                                        ->airportRelationship('dpt_airport')
                                        ->required()
                                        ->afterStateUpdated(self::recalculateRoute(...)),

                                    AirportSelect::make('arr_airport_id')
                                        ->label(__('airports.arrival'))
                                        ->airportRelationship('arr_airport')
                                        ->required()
                                        ->afterStateUpdated(self::recalculateRoute(...)),
                                ])
                                    ->columnSpanFull()
                                    ->columns(2),

                                Textarea::make('route')
                                    ->label(__('flights.route')),

                                Grid::make()->schema([
                                    AirportSelect::make('alt_airport_id')
                                        ->label(__('flights.alternateairport'))
                                        ->airportRelationship('alt_airport'),

                                    TextInput::make('level')
                                        ->label(__('flights.level'))
                                        ->integer()
                                        ->live(onBlur: true)
                                        ->suffix(__('common.feet_short')),

                                    TextInput::make('distance')
                                        ->integer()
                                        ->live(onBlur: true)
                                        ->suffix(__('common.nautical_miles_short')),
                                ])
                                    ->columnSpanFull()
                                    ->columns(3),
                            ])
                            ->columnSpanFull(),

                        Section::make(__('filament.scheduling'))
                            ->id('scheduling')
                            ->icon(TablerIcon::Calendar)
                            ->collapsible()
                            ->persistCollapsed()
                            ->columns(2)
                            ->schema([
                                TimePicker::make('departure_time')
                                    ->seconds(false)
                                    ->label(__('flights.departuretime'))
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(self::recalculateBlockTime(...)),

                                TimePicker::make('arrival_time')
                                    ->seconds(false)
                                    ->label(__('flights.arrivaltime'))
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(self::recalculateBlockTime(...)),

                                DatePicker::make('start_date')
                                    ->label(__('common.start_date'))
                                    ->live()
                                    ->native(false)
                                    ->minDate(fn (?Flight $record): ?Carbon => $record instanceof Flight ? null : now())
                                    ->visible(fn (?Flight $record): bool => !self::parentBundleOwnsDates($record)),

                                DatePicker::make('end_date')
                                    ->label(__('common.end_date'))
                                    ->native(false)
                                    ->minDate(function (Get $get, ?Flight $record): Carbon|string|null {
                                        if ($record instanceof Flight) {
                                            return $get('start_date');
                                        }

                                        return $get('start_date') ?? now();
                                    })
                                    ->visible(fn (?Flight $record): bool => !self::parentBundleOwnsDates($record)),

                                TextEntry::make('bundle_dates_message')
                                    ->visible(fn (?Flight $record): bool => self::parentBundleOwnsDates($record))
                                    ->state(fn (?Flight $record): HtmlString => new HtmlString(self::parentBundleOwnedDatesMessage($record)))
                                    ->html()
                                    ->label('')
                                    ->columnSpanFull(),

                                TimePicker::make('flight_time')
                                    ->seconds(false)
                                    ->label(__('flights.flight_time'))
                                    ->native(false)
                                    ->required()
                                    ->helperText(__('filament.flight_block_time_hint')),

                                ToggleButtons::make('days')
                                    ->label(__('common.days_text'))
                                    ->helperText(__('filament.flight_days_hint'))
                                    ->options(self::dayOptions())
                                    ->multiple()
                                    ->inline()
                                    ->grouped()
                                    ->extraAttributes(['class' => 'daypick'])
                                    ->stateCast(new DaysMaskStateCast())
                                    ->columnSpanFull(),
                            ])
                            ->columnSpanFull(),

                        Section::make(__('filament.flights.sections.loadsheet'))
                            ->id('loadsheet')
                            ->icon(TablerIcon::Scale)
                            ->collapsible()
                            ->persistCollapsed()
                            ->columns(2)
                            ->schema([
                                TextInput::make('load_factor')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->suffix('%')
                                    ->stripCharacters('%')
                                    ->helperText(__('filament.flight_load_factor_hint')),

                                TextInput::make('load_factor_variance')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->suffix('±%')
                                    ->stripCharacters('%')
                                    ->helperText(__('filament.flight_load_factor_variance_hint')),
                            ])
                            ->columnSpanFull(),

                        Section::make(__('common.notes'))
                            ->icon(TablerIcon::Notes)
                            ->collapsible()
                            ->persistCollapsed()
                            ->schema([
                                Textarea::make('notes')
                                    ->hiddenLabel()
                                    ->rows(3)
                                    ->placeholder(__('filament.flight_notes_placeholder'))
                                    ->columnSpanFull(),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),

            ]);
    }

    /**
     * The flight's identity: who operates it and how it is numbered. Shown in
     * the edit page's overview and edited through its drawer, so the two
     * places that render them stay in step.
     *
     * Every element is a Field (Select/TextInput), not just a Component --
     * EditFlight maps over these calling getName(), which only Field has.
     *
     * @return array<int, Field>
     */
    public static function identityFields(): array
    {
        return [
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
                ->options(FlightType::class),

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
        ];
    }

    /**
     * The route band's contents, read from live form state so it tracks the
     * airports and times edited directly beneath it instead of lagging a save.
     *
     * @return array<string, string|int|null>
     */
    public static function routeBarData(Get $get): array
    {
        $departure = self::airport($get('dpt_airport_id'));
        $arrival = self::airport($get('arr_airport_id'));
        $blockTime = self::clockTime($get('flight_time'));

        return [
            // `?->` only where there is no `??` to fall back on: the coalesce
            // already swallows a property read on null, so `$departure?->name ?? ''`
            // is the nullsafe doing nothing.
            'dptIcao'       => $departure?->icao,
            'dptName'       => $departure->name ?? '',
            'arrIcao'       => $arrival?->icao,
            'arrName'       => $arrival->name ?? '',
            'departureTime' => blank($get('departure_time')) ? null : self::clockTime($get('departure_time')),
            'arrivalTime'   => blank($get('arrival_time')) ? null : self::clockTime($get('arrival_time')),
            'blockTime'     => $blockTime === '' ? '—' : $blockTime,
            'distance'      => blank($get('distance')) ? null : (int) $get('distance'),
            'level'         => blank($get('level')) ? null : intdiv((int) $get('level'), 100),
        ];
    }

    private static function airport(mixed $airportId): ?Airport
    {
        return blank($airportId) ? null : Airport::find($airportId);
    }

    /**
     * Both figures a leg carries — block time and distance — belong to the pair
     * of airports, so changing either end has to redo both. Left alone, a
     * re-routed flight keeps quoting the mileage of the leg it used to be.
     */
    public static function recalculateRoute(Get $get, Set $set): void
    {
        foreach (self::routeCalculations([
            'dpt_airport_id' => $get('dpt_airport_id'),
            'arr_airport_id' => $get('arr_airport_id'),
            'departure_time' => $get('departure_time'),
            'arrival_time'   => $get('arrival_time'),
        ]) as $field => $value) {
            $set($field, $value);
        }
    }

    /**
     * @param  array{dpt_airport_id?: mixed, arr_airport_id?: mixed, departure_time?: mixed, arrival_time?: mixed} $data
     * @return array{distance?: int, flight_time?: string}
     */
    public static function routeCalculations(array $data): array
    {
        $calculations = [];
        $departureAirport = $data['dpt_airport_id'] ?? null;
        $arrivalAirport = $data['arr_airport_id'] ?? null;

        if (filled($departureAirport) && filled($arrivalAirport)) {
            $distance = app(AirportService::class)->calculateDistance($departureAirport, $arrivalAirport);

            if ($distance !== null) {
                $calculations['distance'] = (int) round($distance->toUnit('nmi'));
            }
        }

        $blockTime = self::blockTimeFromValues(
            $data['departure_time'] ?? null,
            $data['arrival_time'] ?? null,
            $departureAirport,
            $arrivalAirport,
        );

        if ($blockTime !== null) {
            $calculations['flight_time'] = $blockTime;
        }

        return $calculations;
    }

    /**
     * Derive block time from the scheduled local times and the two airports'
     * timezones, and write it into flight_time.
     *
     * A leg is quoted in each end's local clock, so the elapsed time is only
     * visible once both are pulled to UTC — KATL 06:25 to KAUS 08:15 is 2:50,
     * not the 1:50 the raw clock difference suggests. Both are anchored to the
     * same calendar date so each end resolves its own DST offset for that day.
     *
     * Leaves the existing value alone when anything it needs is missing: a
     * required field is worse blanked than stale.
     */
    public static function recalculateBlockTime(Get $get, Set $set): void
    {
        $blockTime = self::blockTimeFrom($get);

        if ($blockTime !== null) {
            $set('flight_time', $blockTime);
        }
    }

    /** @return string|null `H:i`, or null when it cannot be worked out */
    private static function blockTimeFrom(Get $get): ?string
    {
        return self::blockTimeFromValues(
            $get('departure_time'),
            $get('arrival_time'),
            $get('dpt_airport_id'),
            $get('arr_airport_id'),
        );
    }

    private static function blockTimeFromValues(
        mixed $departure,
        mixed $arrival,
        mixed $departureAirport,
        mixed $arrivalAirport,
    ): ?string {
        if (blank($departure) || blank($arrival)) {
            return null;
        }

        $departureZone = self::airportTimezone($departureAirport);
        $arrivalZone = self::airportTimezone($arrivalAirport);

        if ($departureZone === null || $arrivalZone === null) {
            return null;
        }

        $date = CarbonImmutable::now()->toDateString();

        try {
            $departsAt = CarbonImmutable::parse($date.' '.self::clockTime($departure), $departureZone);
            $arrivesAt = CarbonImmutable::parse($date.' '.self::clockTime($arrival), $arrivalZone);
        } catch (Throwable) {
            return null;
        }

        // An arrival at or before the departure is the next day: red-eyes are
        // ordinary, a zero or negative block time is not.
        if ($arrivesAt <= $departsAt) {
            $arrivesAt = $arrivesAt->addDay();
        }

        $minutes = $departsAt->diffInMinutes($arrivesAt);

        return sprintf('%02d:%02d', intdiv((int) $minutes, 60), (int) $minutes % 60);
    }

    /**
     * A TimePicker hands back either a bare clock time or a full datetime
     * depending on how it was filled; only the clock part is wanted, and it
     * must not be read in the app timezone on the way through.
     */
    private static function clockTime(mixed $value): string
    {
        $value = $value instanceof DateTimeInterface
            ? $value->format('H:i')
            : (string) $value;

        return str_contains($value, ' ')
            ? substr((string) strrchr($value, ' '), 1, 5)
            : substr($value, 0, 5);
    }

    private static function airportTimezone(mixed $airportId): ?string
    {
        if (blank($airportId)) {
            return null;
        }

        $timezone = Airport::whereKey($airportId)->value('timezone');

        return blank($timezone) ? null : (string) $timezone;
    }

    /**
     * Two-letter day labels for the operating-days picker, keyed by the
     * Days::* bitmask constant. Carbon supplies the localised abbreviations
     * (Mo, Tu, We ...) so the row stays narrow in any locale.
     *
     * @return array<int, string>
     */
    private static function dayOptions(): array
    {
        $monday = Carbon::now()->startOfWeek(CarbonInterface::MONDAY);

        $options = [];
        foreach (Days::$isoDayMap as $iso => $mask) {
            $options[$mask] = $monday->copy()->addDays($iso - 1)->minDayName;
        }

        return $options;
    }

    /**
     * Resolve the parent FlightBundle from the record or route.
     *
     * Per-request memoization is provided by Laravel's container; we register
     * a shared instance keyed by the route parameter on first lookup so the 4
     * form closures that consult this method don't each hit the DB. The
     * container is reset between requests, between Pest tests, and per queue
     * job, so stale-instance leaks (the bug a `static` cache would cause when
     * PKs are reused across tests) are not possible.
     */
    private static function resolveParentBundle(?Flight $record = null): ?FlightBundle
    {
        if ($record instanceof Flight) {
            if ($record->relationLoaded('bundle')) {
                $bundle = $record->bundle;
                if ($bundle instanceof FlightBundle) {
                    return $bundle;
                }
            }

            if ($record->bundle_id !== null) {
                return $record->bundle;
            }
        }

        $route = request()->route();
        if ($route !== null) {
            $value = $route->parameter('flight_bundle');
            if ($value instanceof FlightBundle) {
                return $value;
            }

            if (is_scalar($value)) {
                $key = 'phpvms.flight_form.bundle.'.$value;
                if (app()->bound($key)) {
                    return app($key);
                }

                $bundle = FlightBundle::query()->find($value);
                app()->instance($key, $bundle);

                return $bundle;
            }
        }

        return null;
    }

    private static function parentBundleOwnsDates(?Flight $record = null): bool
    {
        $bundle = self::resolveParentBundle($record);

        return $bundle instanceof FlightBundle && $bundle->has_dates;
    }

    private static function parentBundleOwnedDatesMessage(?Flight $record = null): string
    {
        $bundle = self::resolveParentBundle($record);

        if (!$bundle instanceof FlightBundle) {
            return '';
        }

        return __('filament.flights.bundle_owned_dates_message', [
            'bundle' => e($bundle->name),
            'start'  => e($bundle->start_date?->toFormattedDateString() ?? '—'),
            'end'    => e($bundle->end_date?->toFormattedDateString() ?? '—'),
            'url'    => e(FlightBundleResource::getUrl('edit', ['record' => $bundle])),
        ]);
    }

    /**
     * Four-state status badge derived from flight + bundle state.
     *
     * @return array{0: string, 1: string} [label, color]
     */
    public static function flightStatusBadge(Flight $record): array
    {
        if (!$record->enabled) {
            return [__('filament.flights.status.disabled'), 'danger'];
        }

        $bundle = $record->bundle;
        $bundleBlocking = $bundle instanceof FlightBundle
            && ($bundle->deleted_at !== null || !$bundle->enabled);

        if ($bundleBlocking) {
            return [__('filament.flights.status.disabled_by_bundle'), 'danger'];
        }

        if ($record->visible) {
            return [__('filament.flights.status.enabled_in_window'), 'success'];
        }

        return [__('filament.flights.status.enabled_out_of_window'), 'warning'];
    }
}

<?php

namespace App\Filament\Resources\FlightBundles\Resources\Flight\Schemas;

use App\Enums\FlightType;
use App\Filament\Resources\FlightBundles\FlightBundleResource;
use App\Models\Airport;
use App\Models\Flight;
use App\Models\FlightBundle;
use App\Support\Days;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

class FlightForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make()->schema([
                    Section::make(__('filament.flight_information'))
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
                                ->options(FlightType::class),

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
                                ->label(__('flights.flight_time'))
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
                                ->label(''),

                            Select::make('days')
                                ->label(__('common.days_text'))
                                ->options(Days::labels())
                                ->multiple()
                                ->native(false),

                            TimePicker::make('departure_time')
                                ->seconds(false)
                                ->label(__('flights.departuretime')),

                            TimePicker::make('arrival_time')
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

                        TextEntry::make('status_badge')
                            ->label(__('common.status'))
                            ->visible(fn (?Flight $record): bool => $record instanceof Flight)
                            ->badge()
                            ->state(fn (Flight $record): string => self::flightStatusBadge($record)[0])
                            ->color(fn (Flight $record): string => self::flightStatusBadge($record)[1]),

                        Toggle::make('enabled')
                            ->inline()
                            ->label(__('common.enabled'))
                            ->offIcon(Heroicon::XCircle)
                            ->offColor('danger')
                            ->onIcon(Heroicon::CheckCircle)
                            ->onColor('success')
                            ->default(true),
                    ])
                    ->columnSpanFull()
                    ->columns(2),
            ]);
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
    private static function flightStatusBadge(Flight $record): array
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

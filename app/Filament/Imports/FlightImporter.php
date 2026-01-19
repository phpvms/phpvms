<?php

namespace App\Filament\Imports;

use App\Models\Airport;
use App\Models\Enums\Days;
use App\Models\Fare;
use App\Models\Flight;
use App\Models\Subfleet;
use App\Services\AirportService;
use App\Services\FareService;
use App\Services\FlightService;
use App\Support\Utils;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Number;

/**
 * @property Flight $record
 */
class FlightImporter extends Importer
{
    protected static ?string $model = Flight::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('airline')
                ->requiredMapping()
                ->relationship(resolveUsing: 'icao')
                ->rules(['required']),

            ImportColumn::make('flight_number')
                ->requiredMapping()
                ->numeric()
                ->rules(['required', 'integer']),

            ImportColumn::make('callsign')
                ->rules(['max:4']),

            ImportColumn::make('route_code')
                ->rules(['max:5']),

            ImportColumn::make('route_leg')
                ->numeric()
                ->rules(['nullable', 'integer']),

            ImportColumn::make('dpt_airport')
                ->requiredMapping()
                ->fillRecordUsing(function (Flight $record, string $state): void {
                    $record->dpt_airport_id = self::processAirport($state)->id;
                })
                ->rules(['required']),

            ImportColumn::make('arr_airport')
                ->requiredMapping()
                ->fillRecordUsing(function (Flight $record, string $state): void {
                    $record->arr_airport_id = self::processAirport($state)->id;
                })
                ->rules(['required']),

            ImportColumn::make('alt_airport')
                ->fillRecordUsing(function (Flight $record, ?string $state): void {
                    if ($state) {
                        $record->alt_airport_id = self::processAirport($state)->id;
                    }
                }),

            ImportColumn::make('dpt_time')
                ->rules(['max:10']),

            ImportColumn::make('arr_time')
                ->rules(['max:10']),

            ImportColumn::make('level')
                ->numeric()
                ->rules(['nullable', 'integer']),

            ImportColumn::make('distance')
                ->numeric()
                ->rules(['nullable', 'integer']),

            ImportColumn::make('flight_time')
                ->numeric()
                ->rules(['nullable', 'integer']),

            ImportColumn::make('flight_type')
                ->requiredMapping()
                ->rules(['required', 'max:1']),

            ImportColumn::make('load_factor')
                ->numeric()
                ->rules(['nullable', 'integer']),

            ImportColumn::make('load_factor_variance')
                ->numeric()
                ->rules(['nullable', 'integer']),

            ImportColumn::make('route')
                ->fillRecordUsing(function (Flight $record, ?string $state): void {
                    $record->route = strtoupper($state);
                }),

            ImportColumn::make('pilot_pay')
                ->numeric()
                ->rules(['nullable', 'integer']),

            ImportColumn::make('notes'),

            ImportColumn::make('days')
                ->fillRecordUsing(function (Flight $record, ?string $state) {
                    if ($state) {
                        $record->days = self::setDays($state);
                    }
                }),

            ImportColumn::make('start_date')
                ->rules(['nullable', 'date']),

            ImportColumn::make('end_date')
                ->rules(['nullable', 'date']),

            ImportColumn::make('active')
                ->requiredMapping()
                ->boolean()
                ->rules(['required', 'boolean']),

            ImportColumn::make('event')
                ->relationship(),

            ImportColumn::make('user')
                ->relationship(),

            ImportColumn::make('owner_type')
                ->rules(['max:191']),

            ImportColumn::make('owner_id')
                ->rules(['max:36']),

            ImportColumn::make('subfleets')
                ->fillRecordUsing(function (): void {}),

            ImportColumn::make('fares')
                ->fillRecordUsing(function (): void {}),

            ImportColumn::make('fields')
                ->fillRecordUsing(function (): void {}),
        ];
    }

    public function resolveRecord(): Flight
    {
        return Flight::withTrashed()->firstOrNew([
            'flight_number'  => $this->data['flight_number'],
            'dpt_airport_id' => $this->data['dpt_airport'],
            'arr_airport_id' => $this->data['arr_airport'],
            'route_code'     => $this->data['route_code'],
            'route_leg'      => $this->data['route_leg'],
            'days'           => self::setDays($this->data['days']),
        ], [
            'id' => Utils::generateNewId(),
        ]);
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your flight import has completed and '.Number::format($import->successful_rows).' '.str('row')->plural($import->successful_rows).' imported.';

        if (($failedRowsCount = $import->getFailedRowsCount()) !== 0) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to import.';
        }

        return $body;
    }

    protected function beforeUpdate(): void
    {
        if ($this->record->trashed()) {
            $this->record->restore();
        }
    }

    protected function afterSave(): void
    {
        if (array_key_exists('subfleets', $this->data) && $this->data['subfleets'] != '') {
            $this->processSubfleets($this->record, $this->data['subfleets']);
        }

        if (array_key_exists('fares', $this->data) && $this->data['fares'] != '') {
            $this->processFares($this->record, $this->data['fares']);
        }

        if (array_key_exists('fields', $this->data) && $this->data['fields'] != '') {
            $this->processFields($this->record, $this->data['fields']);
        }
    }

    private static function setDays(string $state): int
    {
        if ($state === '' || $state === '0') {
            return 0;
        }

        $days = [];
        if (str_contains($state, '1')) {
            $days[] = Days::MONDAY;
        }

        if (str_contains($state, '2')) {
            $days[] = Days::TUESDAY;
        }

        if (str_contains($state, '3')) {
            $days[] = Days::WEDNESDAY;
        }

        if (str_contains($state, '4')) {
            $days[] = Days::THURSDAY;
        }

        if (str_contains($state, '5')) {
            $days[] = Days::FRIDAY;
        }

        if (str_contains($state, '6')) {
            $days[] = Days::SATURDAY;
        }

        if (str_contains($state, '7')) {
            $days[] = Days::SUNDAY;
        }

        return Days::getDaysMask($days);
    }

    private function processSubfleets(Flight $flight, $col): void
    {
        $count = 0;
        $subfleets = Utils::parseMultiColumnValues($col);
        foreach ($subfleets as $subfleet_type) {
            $subfleet_type = trim($subfleet_type);
            if ($subfleet_type === '' || $subfleet_type === '0') {
                continue;
            }

            $subfleet = Subfleet::firstOrCreate(
                ['type' => $subfleet_type],
                [
                    'name'       => $subfleet_type,
                    'airline_id' => $flight->airline_id,
                ]
            );

            $subfleet->save();

            // sync
            $flight->subfleets()->syncWithoutDetaching([$subfleet->id]);
            $count++;
        }

        Log::info('Subfleets added/processed: '.$count);
    }

    private function processFares(Flight $flight, ?string $state): void
    {
        $fares = Utils::parseMultiColumnValues($state);
        foreach ($fares as $fare_code => $fare_attributes) {
            if (\is_int($fare_code)) {
                $fare_code = $fare_attributes;
                $fare_attributes = [];
            }

            $fare = Fare::firstOrCreate(['code' => $fare_code], ['name' => $fare_code]);
            app(FareService::class)->setForFlight($flight, $fare, $fare_attributes);
            $fare->save();
        }
    }

    /**
     * Parse all of the subfields
     */
    private function processFields(Flight $flight, ?string $col): void
    {
        $pass_fields = [];
        $fields = Utils::parseMultiColumnValues($col);

        foreach ($fields as $field_name => $field_value) {
            $pass_fields[] = [
                'name'  => $field_name,
                'value' => $field_value,
            ];
        }

        app(FlightService::class)->updateCustomFields($flight, $pass_fields);
    }

    private static function processAirport(string $id): Airport
    {
        $airport = app(AirportService::class)->lookupAirportIfNotFound($id);

        if (!$airport) {
            throw new RowImportFailedException('Could not find airport '.$id);
        }

        return $airport;
    }
}

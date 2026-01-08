<?php

namespace App\Filament\Imports;

use App\Models\Aircraft;
use App\Models\Enums\AircraftState;
use App\Support\ICAO;
use App\Support\Units\Mass;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Number;

class AircraftImporter extends Importer
{
    protected static ?string $model = Aircraft::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('subfleet')
                ->requiredMapping()
                ->relationship(resolveUsing: 'type')
                ->rules(['required']),
            ImportColumn::make('icao')
                ->rules(['max:4']),
            ImportColumn::make('iata')
                ->rules(['max:4']),
            ImportColumn::make('airport')
                ->guess(['airport_id'])
                ->relationship(),
            ImportColumn::make('hub')
                ->guess(['hub_id'])
                ->relationship(),
            ImportColumn::make('landing_time')
                ->rules(['nullable', 'datetime']),
            ImportColumn::make('name')
                ->requiredMapping()
                ->rules(['required']),
            ImportColumn::make('registration'),
            ImportColumn::make('fin'),
            ImportColumn::make('hex_code'),
            ImportColumn::make('selcal'),
            ImportColumn::make('dow')
                ->fillRecordUsing(function (Aircraft $record, ?int $state): void {
                    $record->dow = $state > 0 ? Mass::make((float) $state, setting('units.weight')) : null;
                })
                ->numeric()
                ->rules(['nullable', 'integer']),
            ImportColumn::make('mtow')
                ->fillRecordUsing(function (Aircraft $record, ?int $state): void {
                    Log::info('State:'.$state);
                    $record->mtow = $state > 0 ? Mass::make((float) $state, setting('units.weight')) : null;
                })
                ->numeric()
                ->rules(['nullable', 'integer']),
            ImportColumn::make('mlw')
                ->fillRecordUsing(function (Aircraft $record, ?int $state): void {
                    $record->mlw = $state > 0 ? Mass::make((float) $state, setting('units.weight')) : null;
                })
                ->numeric()
                ->rules(['nullable', 'integer']),
            ImportColumn::make('zfw')
                ->fillRecordUsing(function (Aircraft $record, ?int $state): void {
                    $record->zfw = $state > 0 ? Mass::make((float) $state, setting('units.weight')) : null;
                })
                ->numeric()
                ->rules(['nullable', 'integer']),
            ImportColumn::make('simbrief_type'),
            ImportColumn::make('fuel_onboard')
                ->numeric()
                ->rules(['nullable', 'integer']),
            ImportColumn::make('flight_time')
                ->numeric()
                ->rules(['nullable', 'integer']),
            ImportColumn::make('status')
                ->requiredMapping()
                ->rules(['required']),
            ImportColumn::make('state')
                ->numeric()
                ->rules(['required', 'integer']),
        ];
    }

    public function resolveRecord(): Aircraft
    {
        return Aircraft::withTrashed()->firstOrNew([
            'registration' => $this->data['registration'],
        ]);
    }

    protected function beforeSave(): void
    {
        if (!$this->record->hex_code) {
            $this->record->hex_code = ICAO::createHexCode();
        }

        if (!$this->record->state) {
            $this->record->state = AircraftState::PARKED;
        }
    }

    protected function beforeUpdate(): void
    {
        if ($this->record->trashed()) {
            $this->record->restore();
        }
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your aircraft import has completed and '.Number::format($import->successful_rows).' '.str('row')->plural($import->successful_rows).' imported.';

        if (($failedRowsCount = $import->getFailedRowsCount()) !== 0) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to import.';
        }

        return $body;
    }
}

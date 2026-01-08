<?php

namespace App\Filament\Imports;

use App\Models\Aircraft;
use App\Support\ICAO;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
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
                ->relationship(),
            ImportColumn::make('hub')
                ->relationship(),
            ImportColumn::make('landing_time')
                ->rules(['datetime']),
            ImportColumn::make('name')
                ->requiredMapping()
                ->rules(['required']),
            ImportColumn::make('registration'),
            ImportColumn::make('fin'),
            ImportColumn::make('hex_code'),
            ImportColumn::make('selcal'),
            ImportColumn::make('dow')
                ->numeric()
                ->rules(['integer']),
            ImportColumn::make('mtow')
                ->numeric()
                ->rules(['integer']),
            ImportColumn::make('mlw')
                ->numeric()
                ->rules(['integer']),
            ImportColumn::make('zfw')
                ->numeric()
                ->rules(['integer']),
            ImportColumn::make('simbrief_type'),
            ImportColumn::make('fuel_onboard')
                ->numeric()
                ->rules(['integer']),
            ImportColumn::make('flight_time')
                ->numeric()
                ->rules(['integer']),
            ImportColumn::make('status')
                ->requiredMapping()
                ->rules(['required']),
            ImportColumn::make('state')
                ->requiredMapping()
                ->numeric()
                ->rules(['required', 'integer']),
        ];
    }

    public function resolveRecord(): Aircraft
    {
        return Aircraft::firstOrNew([
            'registration' => $this->data['registration'],
        ]);
    }

    protected function beforeSave(): void
    {
        if (!$this->record->hex_code) {
            $this->record->hex_code = ICAO::createHexCode();
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

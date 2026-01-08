<?php

namespace App\Filament\Imports;

use App\Models\Airport;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Number;
use Illuminate\Validation\Rule;
use League\ISO3166\ISO3166;

class AirportImporter extends Importer
{
    protected static ?string $model = Airport::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('iata'),
            ImportColumn::make('icao')
                ->fillRecordUsing(function (Airport $record, string $state): void {
                    $record->id = $state;
                    $record->icao = $state;
                })
                ->requiredMapping()
                ->rules(['required', 'max:4']),
            ImportColumn::make('name')
                ->requiredMapping()
                ->rules(['required']),
            ImportColumn::make('location'),
            ImportColumn::make('region'),
            ImportColumn::make('country')
                ->fillRecordUsing(function (Airport $record, string $state): void {
                    $record->country = strtolower($state);
                })
                ->rules([Rule::in(array_column((new ISO3166())->all(), 'alpha2'))]),
            ImportColumn::make('timezone'),
            ImportColumn::make('hub')
                ->requiredMapping()
                ->boolean()
                ->rules(['required', 'boolean']),
            ImportColumn::make('notes'),
            ImportColumn::make('lat')
                ->numeric(decimalPlaces: 5)
                ->rules(['numeric']),
            ImportColumn::make('lon')
                ->numeric(decimalPlaces: 5)
                ->rules(['numeric']),
            ImportColumn::make('elevation')
                ->integer()
                ->rules(['integer', 'nullable']),
            ImportColumn::make('ground_handling_cost')
                ->numeric()
                ->rules(['numeric', 'nullable']),
            ImportColumn::make('fuel_100ll_cost')
                ->numeric()
                ->rules(['numeric', 'nullable']),
            ImportColumn::make('fuel_jeta_cost')
                ->numeric()
                ->rules(['numeric', 'nullable']),
            ImportColumn::make('fuel_mogas_cost')
                ->numeric()
                ->rules(['numeric', 'nullable']),
        ];
    }

    public function resolveRecord(): Airport
    {
        return Airport::withTrashed()->firstOrNew([
            'id' => $this->data['icao'],
        ]);
    }

    protected function beforeUpdate(): void
    {
        if ($this->record->trashed()) {
            $this->record->restore();
        }
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your airport import has completed and '.Number::format($import->successful_rows).' '.str('row')->plural($import->successful_rows).' imported.';

        if (($failedRowsCount = $import->getFailedRowsCount()) !== 0) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to import.';
        }

        return $body;
    }
}

<?php

namespace App\Filament\Exports;

use App\Models\Aircraft;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class AircraftExporter extends Exporter
{
    protected static ?string $model = Aircraft::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('subfleet.type'),
            ExportColumn::make('icao'),
            ExportColumn::make('iata'),
            ExportColumn::make('airport.id'),
            ExportColumn::make('hub.id'),
            ExportColumn::make('landing_time'),
            ExportColumn::make('name'),
            ExportColumn::make('registration'),
            ExportColumn::make('fin'),
            ExportColumn::make('hex_code'),
            ExportColumn::make('selcal'),
            ExportColumn::make('dow'),
            ExportColumn::make('mtow'),
            ExportColumn::make('mlw'),
            ExportColumn::make('zfw'),
            ExportColumn::make('simbrief_type'),
            ExportColumn::make('fuel_onboard'),
            ExportColumn::make('flight_time'),
            ExportColumn::make('status'),
            ExportColumn::make('state'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your aircraft export has completed and '.Number::format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if (($failedRowsCount = $export->getFailedRowsCount()) !== 0) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}

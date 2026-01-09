<?php

namespace App\Filament\Exports;

use App\Models\Airport;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class AirportExporter extends Exporter
{
    protected static ?string $model = Airport::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('iata'),
            ExportColumn::make('icao'),
            ExportColumn::make('name'),
            ExportColumn::make('location'),
            ExportColumn::make('region'),
            ExportColumn::make('country'),
            ExportColumn::make('timezone'),
            ExportColumn::make('hub'),
            ExportColumn::make('notes'),
            ExportColumn::make('lat'),
            ExportColumn::make('lon'),
            ExportColumn::make('elevation'),
            ExportColumn::make('ground_handling_cost'),
            ExportColumn::make('fuel_100ll_cost'),
            ExportColumn::make('fuel_jeta_cost'),
            ExportColumn::make('fuel_mogas_cost'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your airport export has completed and '.Number::format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if (($failedRowsCount = $export->getFailedRowsCount()) !== 0) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}

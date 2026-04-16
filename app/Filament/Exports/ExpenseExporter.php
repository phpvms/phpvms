<?php

namespace App\Filament\Exports;

use App\Models\Aircraft;
use App\Models\Airport;
use App\Models\Expense;
use App\Models\Subfleet;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class ExpenseExporter extends Exporter
{
    protected static ?string $model = Expense::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('airline.icao'),
            ExportColumn::make('name'),
            ExportColumn::make('amount'),
            ExportColumn::make('type'),
            ExportColumn::make('flight_type'),
            ExportColumn::make('charge_to_user'),
            ExportColumn::make('multiplier'),
            ExportColumn::make('active'),

            ExportColumn::make('ref_model_type')
                ->formatStateUsing(function (Expense $record): string {
                    return $record->ref_model ? $record->ref_model_type : '';
                }),

            ExportColumn::make('ref_model_id')
                ->formatStateUsing(function (Expense $record): string {
                    if ($record->ref_model instanceof Aircraft) {
                        return $record->ref_model->registration;
                    }

                    if ($record->ref_model instanceof Airport) {
                        return $record->ref_model->icao;
                    }

                    if ($record->ref_model instanceof Subfleet) {
                        return $record->ref_model->type;
                    }

                    return '';
                }),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your expense export has completed and '.Number::format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if (($failedRowsCount = $export->getFailedRowsCount()) !== 0) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}

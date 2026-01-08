<?php

namespace App\Filament\Exports;

use App\Models\Subfleet;
use App\Support\Utils;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class SubfleetExporter extends Exporter
{
    protected static ?string $model = Subfleet::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('airline.icao'),
            ExportColumn::make('hub.name'),
            ExportColumn::make('type'),
            ExportColumn::make('simbrief_type'),
            ExportColumn::make('name'),
            ExportColumn::make('cost_block_hour'),
            ExportColumn::make('cost_delay_minute'),
            ExportColumn::make('fuel_type'),
            ExportColumn::make('ground_handling_multiplier'),
            ExportColumn::make('cargo_capacity'),
            ExportColumn::make('fuel_capacity'),
            ExportColumn::make('gross_weight'),
            ExportColumn::make('fares')->formatStateUsing(fn (Subfleet $record): string => self::getFares($record)),
            ExportColumn::make('ranks')->formatStateUsing(fn (Subfleet $record): string => self::getRanks($record)),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your subfleet export has completed and '.Number::format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if (($failedRowsCount = $export->getFailedRowsCount()) !== 0) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }

    /**
     * Return any custom fares that have been made to this flight
     */
    private static function getFares(Subfleet $subfleet): string
    {
        $fares = [];
        foreach ($subfleet->fares as $fare) {
            $fare_export = [];
            if ($fare->pivot->price !== null) {
                $fare_export['price'] = $fare->pivot->price;
            }

            if ($fare->pivot->cost !== null) {
                $fare_export['cost'] = $fare->pivot->cost;
            }

            if ($fare->pivot->capacity !== null) {
                $fare_export['capacity'] = $fare->pivot->capacity;
            }

            $fares[$fare->code] = $fare_export;
        }

        return Utils::objectToMultiString($fares);
    }

    /**
     * Return any ranks that have been linked to this subfleet
     */
    private static function getRanks(Subfleet $subfleet): string
    {
        $ranks = [];
        foreach ($subfleet->ranks as $rank) {
            $rank_export = [];
            if ($rank->pivot->acars_pay !== null) {
                $rank_export['acars_pay'] = $rank->pivot->acars_pay;
            }

            if ($rank->pivot->manual_pay !== null) {
                $rank_export['manual_pay'] = $rank->pivot->manual_pay;
            }

            $ranks[$rank->id] = $rank_export;
        }

        return Utils::objectToMultiString($ranks);
    }
}

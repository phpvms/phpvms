<?php

namespace App\Filament\Exports;

use App\Models\Enums\Days;
use App\Models\Flight;
use App\Support\Utils;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class FlightExporter extends Exporter
{
    protected static ?string $model = Flight::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('airline.icao'),
            ExportColumn::make('flight_number'),
            ExportColumn::make('callsign'),
            ExportColumn::make('route_code'),
            ExportColumn::make('route_leg'),
            ExportColumn::make('dpt_airport.icao'),
            ExportColumn::make('arr_airport.icao'),
            ExportColumn::make('alt_airport.icao'),
            ExportColumn::make('dpt_time'),
            ExportColumn::make('arr_time'),
            ExportColumn::make('level'),
            ExportColumn::make('distance'),
            ExportColumn::make('flight_time'),
            ExportColumn::make('flight_type'),
            ExportColumn::make('load_factor'),
            ExportColumn::make('load_factor_variance'),
            ExportColumn::make('route'),
            ExportColumn::make('pilot_pay'),
            ExportColumn::make('notes'),
            ExportColumn::make('days')
                ->formatStateUsing(fn (Flight $record): string => self::getDays($record)),
            ExportColumn::make('start_date'),
            ExportColumn::make('end_date'),
            ExportColumn::make('active'),
            ExportColumn::make('event.id'),
            ExportColumn::make('user.id'),
            ExportColumn::make('owner_type'),
            ExportColumn::make('owner_id'),
            ExportColumn::make('subfleets')
                ->formatStateUsing(fn (Flight $record): string => self::getSubfleets($record)),
            ExportColumn::make('fares')
                ->formatStateUsing(fn (Flight $record): string => self::getFares($record)),
            ExportColumn::make('fields')
                ->formatStateUsing(fn (Flight $record): string => self::getFields($record)),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your flight export has completed and '.Number::format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if (($failedRowsCount = $export->getFailedRowsCount()) !== 0) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }

    /**
     * Return the days string
     *
     *
     * @return string
     */
    private static function getDays(Flight $flight)
    {
        $days_str = '';

        if ($flight->on_day(Days::MONDAY)) {
            $days_str .= '1';
        }

        if ($flight->on_day(Days::TUESDAY)) {
            $days_str .= '2';
        }

        if ($flight->on_day(Days::WEDNESDAY)) {
            $days_str .= '3';
        }

        if ($flight->on_day(Days::THURSDAY)) {
            $days_str .= '4';
        }

        if ($flight->on_day(Days::FRIDAY)) {
            $days_str .= '5';
        }

        if ($flight->on_day(Days::SATURDAY)) {
            $days_str .= '6';
        }

        if ($flight->on_day(Days::SUNDAY)) {
            $days_str .= '7';
        }

        return $days_str;
    }

    /**
     * Return any custom fares that have been made to this flight
     */
    private static function getFares(Flight $flight): string
    {
        $fares = [];
        foreach ($flight->fares as $fare) {
            $fare_export = [];
            if ($fare->pivot->price) {
                $fare_export['price'] = $fare->pivot->price;
            }

            if ($fare->pivot->cost) {
                $fare_export['cost'] = $fare->pivot->cost;
            }

            if ($fare->pivot->capacity) {
                $fare_export['capacity'] = $fare->pivot->capacity;
            }

            $fares[$fare->code] = $fare_export;
        }

        return Utils::objectToMultiString($fares);
    }

    /**
     * Parse all of the subfields
     */
    private static function getFields(Flight $flight): string
    {
        $ret = [];
        foreach ($flight->field_values as $field) {
            $ret[$field->name] = $field->value;
        }

        return Utils::objectToMultiString($ret);
    }

    /**
     * Create the list of subfleets that are associated here
     */
    private static function getSubfleets(Flight $flight): string
    {
        $subfleets = [];
        foreach ($flight->subfleets as $subfleet) {
            $subfleets[] = $subfleet->type;
        }

        return Utils::objectToMultiString($subfleets);
    }
}

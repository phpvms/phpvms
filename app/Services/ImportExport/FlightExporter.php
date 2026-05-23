<?php

declare(strict_types=1);

namespace App\Services\ImportExport;

use App\Contracts\ImportExport;
use App\Models\Flight;
use App\Support\Days;
use Illuminate\Database\Eloquent\Model;

/**
 * The flight importer can be imported or export. Operates on rows
 */
class FlightExporter extends ImportExport
{
    public string $assetType = 'flight';

    /**
     * Set the current columns and other setup
     */
    public function __construct()
    {
        self::$columns = array_keys(FlightImporter::$columns);
    }

    /**
     * Import a flight, parse out the different rows
     */
    public function export(Model $row): array
    {
        if (!$row instanceof Flight) {
            throw new \InvalidArgumentException('Expected Flight Model');
        }

        $ret = [];
        foreach (self::$columns as $column) {
            $ret[$column] = $row->{$column};
        }

        // Modify special fields
        $ret['airline'] = $row->airline->icao;
        $ret['dpt_airport'] = $row->dpt_airport_id;
        $ret['arr_airport'] = $row->arr_airport_id;
        $ret['distance'] = $row->distance->internal();
        $ret['flight_type'] = $row->flight_type->value;

        // Legacy CSV headers keep their names but the underlying values come
        // from the structured TIME columns, formatted back to the legacy `Hi`.
        $ret['dpt_time'] = $row->departure_time?->format('Hi');
        $ret['arr_time'] = $row->arrival_time?->format('Hi');

        if ($row->alt_airport) {
            $ret['alt_airport'] = $row->alt_airport_id;
        }

        $ret['days'] = $this->getDays($row);
        $ret['fares'] = $this->getFares($row);
        $ret['fields'] = $this->getFields($row);
        $ret['subfleets'] = $this->getSubfleets($row);

        return $ret;
    }

    /**
     * Return the days string
     */
    protected function getDays(Flight $flight): string
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
    protected function getFares(Flight $flight): string
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

        return $this->objectToMultiString($fares);
    }

    /**
     * Parse all of the subfields
     */
    protected function getFields(Flight $flight): string
    {
        $ret = [];
        foreach ($flight->field_values as $field) {
            $ret[$field->name] = $field->value;
        }

        return $this->objectToMultiString($ret);
    }

    /**
     * Create the list of subfleets that are associated here
     */
    protected function getSubfleets(Flight $flight): string
    {
        $subfleets = [];
        foreach ($flight->subfleets as $subfleet) {
            $subfleets[] = $subfleet->type;
        }

        return $this->objectToMultiString($subfleets);
    }
}

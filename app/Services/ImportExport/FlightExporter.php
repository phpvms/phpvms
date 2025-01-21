<?php

namespace App\Services\ImportExport;

use App\Contracts\ImportExport;
use App\Models\Enums\Days;
use App\Models\Flight;

/**
 * The flight importer can be imported or export. Operates on rows
 */
class FlightExporter extends ImportExport
{
    public $assetType = 'flight';

    /**
     * Set the current columns and other setup
     */
    public function __construct()
    {
        self::$columns = array_keys(FlightImporter::$columns);
    }

    /**
     * Import a flight, parse out the different rows
     *
     * @param Flight $flight
     */
    public function export($flight): array
    {
        $ret = [];
        foreach (self::$columns as $column) {
            $ret[$column] = $flight->{$column};
        }

        // Modify special fields
        $ret['airline'] = $flight->airline->icao;
        $ret['dpt_airport'] = $flight->dpt_airport_id;
        $ret['arr_airport'] = $flight->arr_airport_id;
        $ret['distance'] = $flight->distance->internal();

        if ($flight->alt_airport) {
            $ret['alt_airport'] = $flight->alt_airport_id;
        }

        $ret['days'] = $this->getDays($flight);
        $ret['fares'] = $this->getFares($flight);
        $ret['fields'] = $this->getFields($flight);
        $ret['subfleets'] = $this->getSubfleets($flight);

        return $ret;
    }

    /**
     * Return the days string
     *
     *
     * @return string
     */
    protected function getDays(Flight &$flight)
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
    protected function getFares(Flight &$flight): string
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
    protected function getFields(Flight &$flight): string
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
    protected function getSubfleets(Flight &$flight): string
    {
        $subfleets = [];
        foreach ($flight->subfleets as $subfleet) {
            $subfleets[] = $subfleet->type;
        }

        return $this->objectToMultiString($subfleets);
    }
}
